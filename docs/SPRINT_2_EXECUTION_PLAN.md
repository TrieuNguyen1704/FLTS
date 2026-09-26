# Kế hoạch thực thi Sprint 2 FLTS

> Thời gian dự kiến: 09/10/2026–22/10/2026  
> Phạm vi: RAG vertical slice  
> Trạng thái tài liệu: kế hoạch thực thi, không phải bằng chứng Sprint 1 hoặc Sprint 2 đã hoàn thành.

## 1. Nguồn đối chiếu và trạng thái repository

Kế hoạch này được lập từ:

- `PROJECT_CONTEXT.md` và mapping PB–US của User Story v1.1.
- `docs/CODEBASE_GUIDE_VI.md`.
- `docs/reference/C1SE32_FLTS_Sprint2_Capstone.xlsx`, cập nhật ngày 26/09/2026.
- Source và Git status được kiểm tra trực tiếp ngày 26/09/2026.

Sprint 2 workbook xác định 09/10/2026–22/10/2026, 128 giờ, bốn thành viên 32 giờ/người. Sheet `Actual` chưa có giờ thực tế, vì vậy không được suy ra phần trăm hoàn thành. Workbook có task, owner và estimate nhưng **không có cột acceptance criteria hoặc comment chứa acceptance criteria**. Trong tài liệu này, task/owner/estimate được giữ nguyên từ workbook; acceptance criteria được ánh xạ từ User Story v1.1 trong `PROJECT_CONTEXT.md`. Các task `SPR2` không có User Story riêng nên dùng điều kiện bằng chứng vận hành, không được gọi là acceptance criteria của User Story.

### Hiện trạng source đã xác minh

- Docker Compose hiện chỉ có `mysql`, `api`, `ai`, `web`; chưa có ChromaDB hoặc queue worker.
- `ai-service` chỉ có `GET /health`; `requirements.txt` chỉ có FastAPI và Uvicorn.
- Laravel upload file và metadata, luôn tạo `processing_status=uploaded_pending_processing`; chưa gọi FastAPI.
- Chưa có job/queue config, processing-run model, extracted text, chunks, vector references, retrieval hoặc RAG endpoint.
- MySQL hiện có `users`, `courses`, `course_enrollments`, `teaching_documents`.
- Frontend chỉ hiển thị upload/list/delete và trạng thái hiện tại; chưa có retry, processing error, chunk summary hoặc retrieval test.
- Test hiện có 4 PHPUnit feature tests/19 assertions cho Sprint 1; chưa có test AI pipeline.
- `.github/workflows/ci.yml` dùng PHP 8.3, trong khi Docker API đã phải chuyển sang PHP 8.4 do dependency lock hiện yêu cầu PHP >= 8.4.1.
- Git đang ở `main` nhưng **chưa có commit nào**; toàn bộ source là untracked. Chưa có bằng chứng remote, quyền truy cập thành viên, branch protection, pull request, review hoặc CI run.

## 2. Prerequisite Sprint 1 phải xử lý

Các mục P0 dưới đây phải hoàn thành trước khi bắt đầu thay đổi RAG. Chúng không tự động làm Sprint 1 hoàn thành; nhóm vẫn phải đối chiếu toàn bộ PB01–PB14 và ghi Actual theo bằng chứng thật.

| Prerequisite | Owner theo kế hoạch | Điều kiện đóng | Blocker hiện tại |
|---|---|---|---|
| Tạo baseline Git có thể truy vết | Nguyen / cả nhóm xác nhận | Có initial commit được review, remote chung, các thành viên clone/push được; không commit `.env`, upload hoặc dependency/build output | Repo chưa có commit; toàn bộ source untracked |
| Chốt branch/merge rules và branch protection | Nguyen / repository admin | Có ảnh hoặc link cấu hình protection; có ít nhất một PR mẫu được người khác review và merge | Chưa có remote/PR evidence |
| Sửa CI PHP runtime | Khoa — task PB04 trong Sprint 2 workbook | Workflow dùng PHP 8.4 tương thích lockfile; backend test và frontend build xanh trên GitHub Actions | Workflow đang dùng PHP 8.3; chưa có CI run |
| Giữ test isolation | Vuong | PHPUnit trong container vẫn dùng SQLite in-memory và không xóa MySQL demo; có regression evidence | Lỗi đã được sửa local nhưng chưa có CI evidence |
| Chốt Sprint 1 carry-over | All Members | Danh sách PB01–PB14: Done/Partial/Carry-over có bằng chứng; không điền giờ Actual thay thành viên | Sprint 1 vẫn đang hoàn thiện |
| Tạo test fixtures được phép sử dụng | Vuong + Khoa | Có PDF text-based, PDF empty/corrupt, DOCX, DOC hợp lệ/lỗi; không chứa dữ liệu nhạy cảm hoặc tài liệu chưa được phép | Chưa có fixture trong repo |

Không nên để Password Recovery, Admin account UI hoặc các UI Sprint 1 còn thiếu chặn RAG vertical slice nếu nhóm chính thức chuyển chúng thành carry-over riêng. Tuy nhiên Git baseline, CI xanh, quyền truy cập và upload/document ownership là cổng bắt buộc vì Sprint 2 xây trực tiếp trên các phần này.

## 3. Mục tiêu kỹ thuật nhỏ nhất

Một vertical slice tối thiểu được coi là chạy khi Lecturer sở hữu course có thể:

1. Upload một PDF text-based hoặc DOCX hợp lệ.
2. Yêu cầu xử lý và nhận trạng thái thật: pending → processing → processed hoặc failed.
3. FastAPI trích xuất, làm sạch và chunk văn bản, giữ liên kết tới document/page hoặc source locator.
4. FastAPI tạo embedding cho từng chunk và upsert vào ChromaDB local bằng vector ID ổn định.
5. Lecturer gửi một query test qua Laravel; backend kiểm tra token/ownership rồi FastAPI trả top-k chunk gồm score, document/chunk metadata và trích đoạn nguồn.
6. UI hiển thị status/error, chunk summary và retrieval evidence thật.

Luồng trên là retrieval vertical slice. Nó chưa tự động là RAG generation hoàn chỉnh. Chỉ được đánh dấu PB23/US-38 hoàn thành nếu có LLM thật, prompt nhận retrieved context, output có schema được validate và lỗi generation được xử lý.

## 4. Thứ tự triển khai

### Phase 0 — Decision gate và baseline

- Hoàn thành Git/PR/CI prerequisite.
- Chốt các quyết định ở mục 11 bằng ADR hoặc biên bản Sprint Planning.
- Chốt API contract trước khi Laravel/FastAPI làm song song.
- Tạo fixture và expected extraction/retrieval evidence trước khi code.

### Phase 1 — Processing lifecycle trước parser

- PB13: tạo processing-run schema và state machine.
- PB13: tạo Laravel queue job, trigger/retry/status API.
- Dùng database queue tích hợp sẵn của Laravel cho demo; thêm `queue-worker` Compose service và migrations `jobs`/`failed_jobs`. Chưa cần Redis nếu không có nhu cầu tải cao.
- Dùng một fake FastAPI response trong automated contract test, không dựng UI giả như chức năng đã hoàn thành.

State machine đề xuất:

```text
uploaded_pending_processing
  -> processing
      -> processed
      -> failed
failed -> processing        (retry tạo processing run mới)
processed -> processing     (explicit reprocess tạo run mới)
```

`teaching_documents.processing_status` là trạng thái tổng hợp mới nhất. Giai đoạn chi tiết `queued`, `extracting`, `cleaning`, `chunking`, `embedding`, `persisting` nằm trong `document_processing_runs.stage`. Chỉ đặt document là `processed` sau khi MySQL artifacts và Chroma vectors đều được xác nhận.

### Phase 2 — Extraction

- PB15: PDF text-based trước; giữ page number và parser metadata. PDF scan/OCR không thuộc scope.
- PB16: DOCX tiếp theo.
- PB16: legacy DOC là spike riêng. Nếu conversion không ổn định, phải trả lỗi hỗ trợ rõ ràng và ghi blocker; không coi PB16 hoàn thành chỉ vì DOCX chạy.
- Với empty/corrupt/encrypted/unsupported file, lưu error code/message vào processing run và phản ánh `failed` lên document.

### Phase 3 — Cleaning và chunking

- PB17: hàm cleaning thuần, deterministic, có unit test; không xóa nội dung có nghĩa.
- PB18: baseline paragraph/sentence chunking; config có version, size/overlap/unit và được lưu trong mỗi run.
- Chunk phải có document ID, run ID, chunk index, content hash và source locator. Source locator PDF tối thiểu có page; DOC/DOCX có paragraph/section locator khi parser cung cấp được.

### Phase 4 — Embedding và ChromaDB

- PB19: chọn một local embedding model sau decision gate; ghi model name/version và dimension.
- PB20: ChromaDB chạy service riêng, volume riêng, collection được pin tên/version theo environment.
- Vector ID phải deterministic theo run/config/chunk hoặc dùng UUID chunk đã lưu; retry dùng upsert, không tạo vector trùng.
- MySQL giữ canonical chunk text và vector reference; Chroma giữ vector + metadata đủ để lọc theo course/document/run.

### Phase 5 — Retrieval

- PB21: FastAPI embed query bằng cùng model, filter theo course/document được Laravel cho phép, trả top-k có score + source metadata.
- Laravel là public security boundary. FastAPI endpoint nội bộ không nhận user role từ browser và không tự tin một `course_id` do client gửi.
- UI retrieval-test chỉ dành cho Lecturer sở hữu course; không mở cho Student.

### Phase 6 — Evidence-only prototype và PB23 gate

- Workbook xếp ba task PB23: evidence UI, evidence-only endpoint, evidence checks.
- Thực hiện chúng như **retrieval-grounding prototype** nếu LLM/provider chưa được chốt.
- Không gọi evidence-only response là “RAG-generated learning object”. Không đánh dấu US-38 hoàn thành nếu chưa có LLM call, structured output validation và generation error handling.
- PB22 re-ranking không có task trong Sprint 2 workbook dù có trong Product Backlog. Chỉ làm baseline context limit/dedup nếu cần để bảo vệ endpoint; re-ranking comparison là unscheduled/TBD và không được nhận là hoàn thành.

## 5. API contract Laravel ↔ FastAPI

### 5.1 Public Laravel API

Các route đều nằm sau `auth.token`, `role:lecturer` và ownership check giống `DocumentController` hiện tại.

| Method và route đề xuất | Mục đích | Response tối thiểu |
|---|---|---|
| `POST /api/courses/{course}/documents/{document}/processing-runs` | Trigger hoặc reprocess; tạo run mới và dispatch job | `202`, document ID, run ID, status `processing`/queued stage |
| `POST /api/courses/{course}/documents/{document}/processing-runs/{run}/retry` | Retry một run failed bằng run mới, không sửa lịch sử | `202`, old/new run IDs |
| `GET /api/courses/{course}/documents/{document}/processing` | Poll document status, latest run, stage, error, counts/config summary | `200` JSON; không trả stack trace/path nội bộ |
| `POST /api/courses/{course}/retrieval-tests` | Query top-k trong course/document filter | `200`, query, top-k, score, chunk ID, excerpt, source locator |
| `POST /api/courses/{course}/evidence-prototype` | Trả evidence pack phục vụ demo; chưa phải LLM RAG nếu provider chưa chốt | `200`, answer=null hoặc label `evidence_only`, citations, limitations |

### 5.2 Internal FastAPI contract

Baseline nhỏ nhất: Laravel queue job gọi FastAPI đồng bộ; request không giữ HTTP connection của browser. Laravel sở hữu processing state và MySQL, FastAPI sở hữu parsing/embedding/retrieval và giao tiếp ChromaDB.

`POST /internal/v1/documents/process` — `multipart/form-data`:

- `file`: binary lấy từ Laravel storage sau ownership check.
- `run_id`, `document_id`, `course_id`.
- `content_sha256`: idempotency/input integrity.
- `config`: JSON gồm parser, cleaning, chunk và embedding config version.

Success response:

```json
{
  "run_id": "...",
  "document_id": 1,
  "content_sha256": "...",
  "parser": {"name": "TBD", "version": "TBD"},
  "extraction": {"page_count": 3, "char_count": 12000, "normalized_text": "..."},
  "chunk_config": {"version": "...", "size": 0, "overlap": 0, "unit": "TBD"},
  "embedding": {"model": "TBD", "dimension": 0},
  "vector_store": {"provider": "chroma", "collection": "TBD"},
  "chunks": [
    {"index": 0, "text": "...", "content_hash": "...", "vector_id": "...", "source": {"page": 1}}
  ]
}
```

Các số `0`/`TBD` trên là schema placeholder trong tài liệu, không phải config đã chọn. Laravel chỉ commit artifacts và đặt `processed` khi response hợp lệ, `run_id`/checksum khớp và số vector/chunk nhất quán. Retry phải upsert cùng deterministic vector IDs. Nếu Laravel persist thất bại sau Chroma upsert, retry được phép ghi đè cùng IDs thay vì nhân bản.

Error response chuẩn:

```json
{
  "error": {
    "code": "PDF_EMPTY_TEXT",
    "message": "No extractable text was found.",
    "retryable": false,
    "stage": "extracting"
  }
}
```

Mã cần phân biệt tối thiểu: unsupported/corrupt/encrypted/empty, parser failure, embedding failure, Chroma unavailable, invalid config và internal error. Không trả raw exception cho UI.

Retrieval nội bộ:

- `POST /internal/v1/retrieval/search` với `query`, `top_k`, filter `course_id`, optional `document_ids`, config version.
- Response gồm `vector_id`, `score`, `document_id`, `chunk_index`, `text/excerpt`, `source`, model/collection version.
- Laravel đối chiếu mọi document ID với course sở hữu trước và sau khi gọi FastAPI.

Internal endpoints cần service token riêng trong environment hoặc network-level control. Browser không gọi FastAPI internal API trực tiếp. Cơ chế service authentication cụ thể vẫn cần nhóm chốt.

## 6. Migration và database tables

| Table/migration đề xuất | Trường chính | Mục đích |
|---|---|---|
| `document_processing_runs` | document_id, attempt, status, stage, input_sha256, parser/config JSON, started_at, finished_at, error_code/message, retry_of_id, counters | Lịch sử mỗi lần process/retry; không ghi đè bằng chứng cũ |
| `document_extractions` | processing_run_id unique, parser_name/version, raw_text hoặc normalized_text LONGTEXT, page/segment count, char count, metadata JSON | Lưu extracted result để bước sau và debug có kiểm soát |
| `document_chunks` | processing_run_id, document_id, course_id, chunk_index, text LONGTEXT, content_hash, size/tokens, source_locator JSON, chunk_config_version | Canonical chunks và citation/source linkage trong MySQL |
| `document_vector_references` | chunk_id unique, vector_id unique, collection, embedding_model/version, dimension, embedded_at | Liên kết MySQL chunk với vector Chroma; không lưu vector lớn trong MySQL |
| Laravel queue tables | `jobs`, `job_batches` nếu dùng, `failed_jobs` | Queue/retry evidence cho `queue-worker` |
| Alter `teaching_documents` nếu cần | latest_processing_run_id nullable, processed_at | Truy cập run hiện tại nhanh; giữ `processing_error` chỉ như summary mới nhất |

Khuyến nghị không nhồi mọi stage mới vào enum `teaching_documents.processing_status`. Giữ summary state nhỏ (`uploaded_pending_processing`, `processing`, `processed`, `failed`) và dùng `document_processing_runs.stage` dạng string/application enum để thêm stage không cần sửa enum MySQL liên tục.

Quan hệ và uniqueness bắt buộc:

- Mỗi run thuộc đúng một document; mỗi extraction thuộc một run.
- `(processing_run_id, chunk_index)` unique.
- `content_hash` và config version được lưu để kiểm tra retry/reprocess.
- `vector_id` unique; vector metadata luôn chứa course/document/run/chunk identifiers.
- Xóa document phải có lifecycle xóa Chroma vectors và MySQL artifacts. Nếu Chroma unavailable, phải ghi cleanup pending/failed thay vì âm thầm để orphan vectors.

## 7. Docker service và package cần chuẩn bị

Phần này là kế hoạch, chưa cài package.

### Compose services/volumes

| Thành phần | Thay đổi dự kiến |
|---|---|
| `chroma` | Image ChromaDB được pin version sau spike; port chỉ expose khi cần debug; healthcheck; volume `chroma_data`; AI depends_on healthy |
| `queue-worker` | Reuse image Laravel API; command `php artisan queue:work` với timeout/tries được chốt; cùng DB và document volume; restart policy cho local demo |
| `ai` | Thêm Chroma host/port, embedding/parser config, service token; health/readiness phân biệt FastAPI sống với Chroma/model sẵn sàng |
| volumes | Giữ `document_storage`; thêm `chroma_data`. Baseline contract gửi multipart nên AI không cần mount file volume; nếu nhóm đổi sang shared-volume contract phải mount read-only và ghi ADR |

### Python/system dependencies dự kiến

| Nhu cầu | Candidate cho spike | Quyết định cần chốt |
|---|---|---|
| PDF text extraction | `pypdf` | Version pin; encrypted/empty behavior; không OCR |
| DOCX extraction | `python-docx` | Cách giữ paragraph/table/source locator |
| Legacy DOC | LibreOffice Writer headless conversion hoặc `antiword` spike | Độ lớn image, fidelity, license, error handling; chọn một hoặc ghi limitation |
| Chroma client | `chromadb` | Client/server version phải tương thích và được pin |
| Local embedding | `sentence-transformers` hoặc thư viện tương ứng model đã duyệt | Exact model, dimension, download/cache, CPU/RAM, license |
| HTTP/config/test | `httpx`, `pydantic-settings`, `pytest` | Exact versions và test boundary |

Không thêm LLM SDK khi provider/model chưa được phê duyệt. Không download model ngầm mỗi lần container start; cần model cache volume hoặc image strategy được ghi rõ sau spike.

## 8. Acceptance criteria mapping

Các mã dưới đây lấy từ User Story v1.1, không phải cột trong Sprint workbook:

- **AC04:** test structure tồn tại; basic tests chạy; integration-test foundation được thiết lập.
- **AC13:** metadata/status hiển thị; status đổi theo pipeline; processing error hiển thị rõ.
- **AC15:** PDF hợp lệ đọc được; text được extract; lỗi file được xử lý; kết quả lưu cho bước sau.
- **AC16:** DOC và DOCX hợp lệ đọc/extract được; lỗi formatting được xử lý.
- **AC17:** noise cơ bản được loại; whitespace/characters normalize; nội dung có nghĩa được giữ.
- **AC18:** chunk có size phù hợp; có document metadata; không mất nội dung quan trọng; config được lưu.
- **AC19:** mỗi chunk hợp lệ có embedding; lỗi embedding được xử lý; metadata linkage được giữ.
- **AC20:** vector + metadata được lưu; query được; vector liên kết source document.
- **AC21:** query được embed; trả top-k; result có score + metadata.
- **AC23:** prompt nhận retrieved context; LLM trả required structure; output được validate; generation error được xử lý.

## 9. Task matrix theo Sprint 2 workbook

| PB | Task | Owner | Estimate | Acceptance/evidence áp dụng |
|---|---|---:|---:|---|
| SPR2 | Sprint Planning Meeting | All Members | 4h | Có biên bản, decision log, risk và dependency; không tính là feature Done |
| SPR2 | Create Sprint 2 Backlog | All Members | 2h | Backlog được nhóm xác nhận; PB/US mapping và carry-over rõ |
| SPR2 | Create / update Sprint 2 Test Plan | Vuong | 2h | Test matrix gắn PB/AC/fixture/evidence; AC04 |
| PB04 | Fix CI PHP version and run GitHub Actions workflow | Khoa | 4h | AC04; PHP 8.4, backend tests + frontend build xanh trên remote PR |
| PB13 | Design processing-run schema and document status state machine | Nguyen | 3h | AC13; schema/state/retry rules được review và migration testable |
| PB13 | Implement Laravel trigger, queue job, retry and status API | Nguyen | 4h | AC13; real transitions/error, authorization, idempotent retry |
| PB13 | Display processing status and errors in Lecturer UI | Hung | 1h | AC13; UI đọc API thật, không giả status/error |
| PB15 | Implement text-based PDF extraction with page metadata | Khoa | 6h | AC15; text + page locator được lưu cho bước sau |
| PB15 | Test valid, empty and corrupt PDF handling | Vuong | 4h | AC15; fixtures và expected error codes có automated evidence |
| PB15 | Implement PDF extraction error mapping and persistence | Khoa | 6h | AC15/AC13; run failed đúng stage, UI-safe error được lưu |
| PB16 | Implement DOCX extraction | Khoa | 5h | AC16; valid DOCX text/source locator được extract |
| PB16 | Implement legacy DOC conversion/extraction spike | Khoa | 4h | AC16; spike có result/decision; nếu không extract được thì PB16 chưa Done |
| PB16 | Test DOC/DOCX fixtures and error cases | Vuong | 3h | AC16; valid/corrupt/format errors được kiểm tra |
| PB17 | Implement text cleaning and normalization | Khoa | 4h | AC17; deterministic cleaning, giữ meaningful content |
| PB17 | Write cleaning/normalization unit tests | Vuong | 4h | AC17; whitespace, Unicode và retention fixtures pass |
| PB18 | Define chunk configuration version and metadata schema | Nguyen | 3h | AC18; config/version/source linkage được lưu |
| PB18 | Implement baseline paragraph/sentence chunking | Khoa | 4h | AC18; boundaries/overlap theo config đã chốt |
| PB18 | Test chunk boundaries, overlap and source linkage | Vuong | 3h | AC18; không mất/nhân nội dung ngoài overlap dự kiến |
| PB18 | Display chunk summary in Lecturer UI | Hung | 2h | AC18; count/config/source summary từ API thật |
| PB19 | Integrate local embedding model with ChromaDB | Vuong | 8h | AC19; mỗi valid chunk có vector/linkage, model metadata được ghi |
| PB19 | Wire FastAPI embedding workflow and configuration | Khoa | 3h | AC19; config không hardcode rải rác, same model cho chunk/query |
| PB19 | Handle embedding errors and record processing failure | Vuong | 3h | AC19/AC13; failure không để document `processed` |
| PB20 | Configure persistent ChromaDB collection/volume | Vuong | 5h | AC20; restart giữ vector, readiness và volume được kiểm tra |
| PB20 | Add vector-reference migration and model linkage | Nguyen | 3h | AC20; MySQL chunk ↔ Chroma vector ↔ source document truy vết được |
| PB20 | Implement vector upsert/delete lifecycle | Khoa | 4h | AC20; retry không duplicate; delete/reprocess không để stale vector |
| PB21 | Implement similarity search and Top-K result formatting | Vuong | 6h | AC21; embedded query, top-k, score + metadata |
| PB21 | Implement authorized Laravel retrieval-test API | Nguyen | 4h | AC21 + ownership; Student/other Lecturer bị chặn |
| PB21 | Build Lecturer retrieval-test UI | Hung | 4h | AC21; query/results/citations từ backend thật, loading/error rõ |
| PB21 | Integration test retrieval, course access and source metadata | Vuong | 4h | AC21; filter không rò dữ liệu course/document khác |
| PB23 | Build evidence-retrieval prototype UI with citations | Hung | 4h | Retrieval evidence only; **không đủ AC23 nếu chưa có LLM/schema** |
| PB23 | Create evidence-only RAG prototype endpoint | Nguyen | 2h | Trả `mode=evidence_only` và citations; chỉ đáp ứng scaffold, không tự đánh dấu PB23 Done |
| PB23 | Run demo evidence checks and document limitations | Vuong | 2h | Ghi input/output/config/log; không bịa accuracy/quality claim |
| SPR2 | Integrate Sprint 2 modules and resolve conflicts | All Members | 4h | Compose vertical slice chạy xuyên suốt; PR/review evidence |
| SPR2 | Run regression tests and fix defects | All Members | 2h | Sprint 1 tests + Sprint 2 test suite pass; kết quả lệnh được lưu |
| SPR2 | Conduct Sprint Review and Retrospective | All Members | 2h | Demo evidence, PB status, blocker/carry-over và retrospective được ghi |

## 10. Test plan và demo evidence

### Automated tests

- Parser unit tests: PDF valid/multi-page/empty/corrupt/encrypted; DOCX text/table/empty/corrupt; DOC conversion success/failure theo quyết định spike.
- Cleaning unit tests: whitespace, line breaks, Unicode, control characters và mẫu chứng minh meaningful content còn giữ.
- Chunk tests: boundary, overlap, deterministic order/hash, source locator, config version, empty input.
- Embedding/Chroma integration: dimension đúng model, one vector per valid chunk, metadata/filter, persistence qua restart, upsert retry không duplicate, delete lifecycle.
- Retrieval tests: query embedding, top-k count/order, score + metadata, course/document filter, no cross-course leakage.
- Laravel feature tests: owner trigger/retry/status/retrieval; other Lecturer/Student bị 403; document/run mismatch 404; validation top-k/query.
- Queue tests: job success/failure/retry, FastAPI timeout/unavailable, malformed response, persistence failure sau vector upsert.
- End-to-end Compose test: upload fixture → trigger → poll → processed → query → citation đúng source; và corrupt fixture → failed/error hiển thị.
- Regression: 4 Sprint 1 tests/19 assertions và Vue build phải tiếp tục pass. Không dùng test database MySQL demo.

### Evidence phải lưu cho Sprint Review

- Commit/PR/reviewer và GitHub Actions run URL/screenshot.
- `docker compose ps`, health/readiness của api/ai/chroma/mysql/web/queue-worker.
- Lệnh test và số test/assertion thực tế; không copy số cũ nếu suite đã thay đổi.
- Fixture ID/hash, parser/config/chunk/model/collection versions.
- Processing run timeline và error example.
- Query, top-k response, score, chunk/source locator và ảnh UI.
- Chứng minh restart Chroma không mất vector và retry không tạo duplicate.
- Limitations: text-based PDF only, DOC spike status, CPU/time, chưa OCR, chưa re-ranking, LLM/RAG status.

### Demo 5 phút đề xuất

1. Đăng nhập Lecturer, mở course và upload fixture đã được phép dùng.
2. Trigger processing; xem stage/status thật chuyển đổi.
3. Mở summary extraction/chunks; nêu parser/config versions.
4. Nhập một query retrieval, xem top-k score và citation page/source.
5. Mở một file corrupt/empty đã chuẩn bị để cho thấy failed/error handling.
6. Nếu chỉ có evidence prototype, nói rõ không có LLM generation. Chỉ demo RAG answer khi AC23 đã thực sự được đáp ứng.

## 11. Decision log còn TBD

| Quyết định | Trạng thái/xung đột | Cần nhóm xác nhận trước |
|---|---|---|
| ChromaDB local | Sprint 2 workbook ghi cụ thể ChromaDB; master context vẫn ghi vector DB TBD | Chroma là lựa chọn demo Sprint 2 hay quyết định kiến trúc chính thức; pin image/client version |
| Embedding model/provider | **Quyết định 26/09:** OpenAI `text-embedding-3-small`; workbook ghi local model nên thay đổi này phải được nêu rõ khi báo cáo | Default dimension 1536 hay dimension reduction, API key/billing limit, privacy, retry/timeout; không tải local model |
| PDF parser | TBD | `pypdf` hoặc lựa chọn khác; scope encrypted/scanned PDF; OCR vẫn out of scope |
| DOCX parser | TBD | `python-docx` hoặc lựa chọn khác; table/heading/source locator policy |
| Legacy DOC | Spike bắt buộc | LibreOffice vs antiword; image size/fidelity; fallback/error message |
| Chunking | TBD | Unit, size, overlap, sentence/paragraph fallback, config version |
| Retrieval | TBD | Similarity metric, top-k default/max, score semantics, filters |
| Re-ranking PB22 | Có trong Product Backlog nhưng không có task Sprint 2 workbook | Defer chính thức hay thêm scope/cắt task khác; không làm ngầm |
| LLM/provider | **Quyết định 26/09:** OpenAI `gpt-4.1-mini` | API key/billing limit, privacy, timeout/retry, output JSON Schema và cách xử lý refusal/lỗi; chưa thêm SDK cho đến PB23 |
| PB23 definition | Workbook chỉ evidence-only; US-38 yêu cầu LLM + structured output | Prototype-only hay cam kết full US-38 trong Sprint 2 |
| Internal authentication | Chưa có | Shared service token, network isolation, secret management |
| Queue | Chưa có | Database queue baseline, tries/backoff/timeout và failed-job handling |
| Artifact retention | Chưa có | Giữ bao nhiêu processing runs/text/chunks; reprocess/delete cleanup |
| Research evidence | Chưa chốt dataset/ground truth/metrics | Sprint 2 chỉ log reproducible evidence; không công bố chất lượng khi chưa có evaluation design |

## 12. Blockers tại thời điểm lập kế hoạch

1. Repository chưa có commit và chưa có remote/PR evidence; CI không thể được xem là đã vận hành.
2. CI PHP 8.3 không khớp runtime/dependency hiện tại PHP 8.4.
3. Sprint 1 chưa đóng và sheet Actual chưa có bằng chứng thực tế.
4. Workbook không chứa acceptance criteria; phải dùng User Story v1.1 và ghi rõ nguồn.
5. ChromaDB/local embedding được workbook gọi tên nhưng xung đột với master decision log còn TBD.
6. PB23 task wording không đủ AC của US-38; LLM/provider và output schema chưa chốt.
7. PB22 không được phân task/giờ trong Sprint 2 workbook.
8. Chưa có parser, queue, Chroma, model, test fixtures hoặc AI integration trong source hiện tại.

Không blocker nào ở trên là bằng chứng Sprint 2 thất bại; đây là điều kiện cần xử lý hoặc xác nhận trước khi nhóm tuyên bố các PB tương ứng hoàn thành.

## 13. Cập nhật trước khi bắt đầu Sprint 2 — 26/09/2026

Các dòng blocker ở phần 12 là snapshot tại thời điểm lập plan, không còn là trạng thái hiện tại cho các điểm dưới đây:

- Repository đã có remote GitHub, branch `main`, PR evidence và CI chạy xanh cho `backend`/`frontend`; workflow hiện dùng PHP 8.4. Một job `backend-quality` (PHP syntax lint) đã được thêm vào source và cần chạy xanh trên PR tiếp theo trước khi gắn vào ruleset required check.
- Sprint 1 code closeout đã thêm registration, password recovery, account management, course update và document search/download. Xem `docs/SPRINT_1_CLOSEOUT_STATUS.md` để biết bằng chứng và giới hạn.
- Sprint 1 chưa được phép gọi là hoàn tất toàn bộ: PB13 state transition/error vẫn là carry-over có chủ đích sang Sprint 2; Actual workbook và bằng chứng team-clone/PR review phải do nhóm bổ sung theo thực tế.
- Quyết định OpenAI vẫn chỉ là direction đã ghi: `text-embedding-3-small` và `gpt-4.1-mini`. ChromaDB vẫn cần nhóm xác nhận và pin version; chưa có SDK/API key nào trong repository.
