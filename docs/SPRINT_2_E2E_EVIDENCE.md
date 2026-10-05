# Sprint 2 — E2E evidence runbook

> This is a reproducible evidence template, not evidence that the live Gemini path has already passed. Complete it only after the exposed key has been revoked and a newly issued key has been placed in the ignored local `.env` file.

## Safety gate

1. Revoke the key that was pasted into chat in Google AI Studio.
2. Copy `.env.example` to `.env` if needed. Set a newly created `GEMINI_API_KEY` and replace `AI_SERVICE_TOKEN` with a local random value. Do not paste either value into this file, a commit, a screenshot, or a PR.
3. Confirm that `git status --short` does not list `.env`.

## Preflight

```powershell
# Rebuild all images so the queue worker, AI service and frontend use the current source.
docker compose up --build -d

# Verify every required service; api, mysql, chroma and mailpit should become healthy.
docker compose ps

# Run the deterministic regression suites before consuming a Gemini request.
docker compose exec -T api php vendor/bin/phpunit --testdox
docker compose exec -T ai pytest -q
```

Record the date/time, commit SHA, Docker Compose output, test counts, `GEMINI_EMBEDDING_MODEL`, `GEMINI_GENERATION_MODEL`, `CHROMA_COLLECTION`, and Chroma image/client versions in the Sprint Review notes.

## Lecturer happy path

1. Sign in as a Lecturer and open a course owned by that account.
2. Upload one permitted, non-sensitive text-based PDF and one permitted DOCX. Record a SHA-256 hash of each fixture outside this repository if the source cannot be committed.
3. In Course Detail, trigger processing for one document. Record the document ID and processing-run ID. Poll until the run is `processed` or `failed`; do not call a timeout a success.
4. For a successful run, save the displayed parser metadata, chunk count, chunk configuration, embedding model/dimension and vector collection.
5. Run one retrieval query. Save the request wording, top-k results, score, document ID and page/paragraph/table source locator. Confirm every result belongs to the selected course.
6. Request the evidence prototype. It is acceptable only if it has the expected structured fields and all citations refer to returned retrieval context. Record any Gemini refusal/error as a failed evidence run, not a successful generated answer.

## Required failure and authorization evidence

- Upload or process an allowed empty/corrupt fixture where permitted; confirm `failed` status, a safe error code/message and retry availability.
- Sign in as a Student and as a second Lecturer. Confirm they cannot trigger, read run status, retrieve, or request evidence for a course/document they do not own.
- Retry a failed run once and confirm the resulting run/attempt history is preserved.

## Chroma lifecycle evidence

1. With a successfully processed document, run the same retrieval once and record the response.
2. Restart only Chroma: `docker compose restart chroma`.
3. Wait for `docker compose ps` to show Chroma healthy, then run the same retrieval again.
4. Confirm the stored vector remains queryable and no cross-course result appears. Record both outputs.
5. Reprocess/delete only through the application flow when that lifecycle is being demonstrated; check for stale vectors with a retrieval query afterward.

## Completion record

| Field | Fill during a real run |
|---|---|
| Date, commit, operator | |
| Fixture IDs and hashes | |
| Successful run ID(s) and stage timeline | |
| Failed run ID(s), error code and retry result | |
| Retrieval query/citations and course-isolation check | |
| Evidence schema/citation validation result | |
| Chroma restart result | |
| Container/test output references | |
| Remaining limitation/blocker | |

The Sprint 2 PB/US status may be upgraded only after these real outputs are reviewed against the workbook and User Story acceptance criteria. OCR, re-ranking, quality evaluation, quiz generation, publishing and analytics are outside this E2E slice.
