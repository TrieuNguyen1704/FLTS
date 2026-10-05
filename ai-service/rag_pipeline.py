"""Small, testable primitives for the Sprint 2 document-processing vertical slice."""

from __future__ import annotations

import hashlib
import re
import subprocess
import tempfile
import unicodedata
from dataclasses import dataclass
from io import BytesIO
from typing import Any

from docx import Document as DocxDocument
from pypdf import PdfReader


class PipelineError(Exception):
    def __init__(self, code: str, message: str, stage: str, status_code: int = 422):
        super().__init__(message)
        self.code = code
        self.stage = stage
        self.status_code = status_code


@dataclass
class ExtractedDocument:
    text: str
    page_count: int | None
    metadata: dict[str, Any]


def extract_document(content: bytes, extension: str) -> ExtractedDocument:
    extension = extension.lower().lstrip('.')
    if extension == 'pdf':
        return _extract_pdf(content)
    if extension == 'docx':
        return _extract_docx(content)
    if extension == 'doc':
        return _extract_doc(content)
    raise PipelineError('UNSUPPORTED_FILE_TYPE', 'Only PDF and DOCX are processable in this Sprint 2 slice.', 'extracting')


def _extract_pdf(content: bytes) -> ExtractedDocument:
    try:
        reader = PdfReader(BytesIO(content))
        if reader.is_encrypted:
            raise PipelineError('PDF_ENCRYPTED', 'Password-protected PDFs cannot be processed.', 'extracting')
        pages = []
        for index, page in enumerate(reader.pages, start=1):
            page_text = (page.extract_text() or '').strip()
            if page_text:
                pages.append(f'[Page {index}]\n{page_text}')
    except PipelineError:
        raise
    except Exception as exception:  # pypdf exposes several version-specific parser exceptions.
        raise PipelineError('PDF_EXTRACTION_FAILED', 'The PDF could not be read as text.', 'extracting') from exception

    text = clean_text('\n\n'.join(pages))
    if not text:
        raise PipelineError('NO_EXTRACTABLE_TEXT', 'No extractable text was found. Scanned PDFs need OCR, which is not included in Sprint 2.', 'extracting')
    return ExtractedDocument(text=text, page_count=len(reader.pages), metadata={'parser': 'pypdf'})


def _extract_docx(content: bytes) -> ExtractedDocument:
    try:
        document = DocxDocument(BytesIO(content))
    except Exception as exception:
        raise PipelineError('DOCX_EXTRACTION_FAILED', 'The DOCX file could not be read.', 'extracting') from exception

    # Mark source boundaries before chunking so citations can identify a DOCX paragraph or table.
    parts = [f'[Paragraph {index}]\n{paragraph.text}' for index, paragraph in enumerate(document.paragraphs, start=1) if paragraph.text.strip()]
    for table_index, table in enumerate(document.tables, start=1):
        rows = [' | '.join(cell.text.strip() for cell in row.cells) for row in table.rows]
        if any(rows):
            parts.append(f'[Table {table_index}]\n' + '\n'.join(rows))
    text = clean_text('\n\n'.join(parts))
    if not text:
        raise PipelineError('NO_EXTRACTABLE_TEXT', 'No extractable text was found in this DOCX document.', 'extracting')
    return ExtractedDocument(text=text, page_count=None, metadata={'parser': 'python-docx', 'table_count': len(document.tables)})


def _extract_doc(content: bytes) -> ExtractedDocument:
    """Extract legacy binary Word via antiword; errors remain explicit because fidelity is format-dependent."""
    try:
        with tempfile.NamedTemporaryFile(suffix='.doc') as source:
            source.write(content)
            source.flush()
            result = subprocess.run(['antiword', source.name], capture_output=True, text=True, timeout=30, check=False)
    except FileNotFoundError as exception:
        raise PipelineError('DOC_PARSER_UNAVAILABLE', 'The legacy DOC parser is not installed in the AI service.', 'extracting', 503) from exception
    except subprocess.TimeoutExpired as exception:
        raise PipelineError('DOC_EXTRACTION_TIMEOUT', 'Legacy DOC extraction exceeded the 30-second limit.', 'extracting', 422) from exception
    except Exception as exception:
        raise PipelineError('DOC_EXTRACTION_FAILED', 'The DOC file could not be read.', 'extracting') from exception

    if result.returncode != 0:
        raise PipelineError('DOC_EXTRACTION_FAILED', 'The legacy DOC file could not be read by the parser.', 'extracting')
    text = clean_text(result.stdout)
    if not text:
        raise PipelineError('NO_EXTRACTABLE_TEXT', 'No extractable text was found in this DOC document.', 'extracting')
    return ExtractedDocument(text=text, page_count=None, metadata={'parser': 'antiword'})


def clean_text(value: str) -> str:
    normalized = unicodedata.normalize('NFKC', value).replace('\x00', '')
    normalized = re.sub(r'[\t\r ]+', ' ', normalized)
    normalized = re.sub(r' *\n *', '\n', normalized)
    return re.sub(r'\n{3,}', '\n\n', normalized).strip()


def chunk_text(text: str, target_words: int = 220, overlap_words: int = 30) -> list[dict[str, Any]]:
    """Chunk by paragraph windows so sources stay readable while a small word overlap protects context."""
    paragraphs = [part.strip() for part in re.split(r'\n{2,}', text) if part.strip()]
    chunks: list[dict[str, Any]] = []
    current: list[str] = []
    current_words = 0

    def append_current() -> None:
        nonlocal current, current_words
        if not current:
            return
        content = '\n\n'.join(current).strip()
        source = re.search(r'^\[(Page|Paragraph|Table) (\d+)\]', content)
        chunks.append({
            'chunk_index': len(chunks),
            'content': content,
            'source_locator': f'{source.group(1).lower()} {source.group(2)}' if source else None,
            'token_estimate': max(1, round(len(content.split()) * 1.3)),
            'content_hash': hashlib.sha256(content.encode('utf-8')).hexdigest(),
        })
        tail = content.split()[-overlap_words:] if overlap_words else []
        current = [' '.join(tail)] if tail else []
        current_words = len(tail)

    for paragraph in paragraphs:
        words = paragraph.split()
        if current and current_words + len(words) > target_words:
            append_current()
        # Very long source paragraphs are split only as a last resort, keeping the algorithm deterministic.
        while len(words) > target_words:
            segment, words = words[:target_words], words[target_words - overlap_words:]
            current.append(' '.join(segment))
            append_current()
        current.append(' '.join(words))
        current_words += len(words)
    append_current()
    return chunks
