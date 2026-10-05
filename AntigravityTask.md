# FLTS — AI Agents Collaboration & Task Sync (Antigravity ↔ Codex)

> **Mục đích:** File này là nhật ký bàn giao công việc (Handoff Log) và đồng bộ tiến độ giữa các AI Agent (**Antigravity** và **Codex**) cùng làm việc trên dự án **FLTS**.  
> **Quy tắc cho Agent:** 
> 1. Đọc file này cùng với [`PROJECT_CONTEXT.md`](PROJECT_CONTEXT.md) trước khi bắt đầu bất kỳ tác vụ nào.
> 2. Cập nhật file này sau mỗi phiên làm việc để Agent tiếp theo nắm bắt chính xác hiện trạng.
> 3. Tuyệt đối **không ghi các thông tin nhạy cảm** (API Key, Mật khẩu ứng dụng, Token thật) vào file này.

---

## 1. Trạng thái hiện tại của hệ thống (Snapshot: 26/09/2026)

* **Giai đoạn:** Đã đóng gói Sprint 1 (Foundation), sẵn sàng bước vào Sprint 2 (RAG Vertical Slice theo [`docs/SPRINT_2_EXECUTION_PLAN.md`](docs/SPRINT_2_EXECUTION_PLAN.md)).
* **Hạ tầng Docker:** 5 containers đang chạy:
  * `flts-web-1` (Vue 3 + Vite, Nginx proxy port 8080)
  * `flts-api-1` (Laravel 12 / PHP 8.4 port 8000)
  * `flts-mysql-1` (MySQL 8.4 port 3306, volume `mysql_data`)
  * `flts-mailpit-1` (Mailpit port 8025 / 1025)
  * `flts-ai-1` (FastAPI port 8001, hiện là placeholder `/health`)
* **Test Suite:** PHPUnit `docker compose exec -T api php vendor/bin/phpunit` pass **7/7 tests, 49 assertions**.

---

## 2. Nhật ký công việc vừa hoàn thành (Phiên làm việc: 26/09/2026 — Antigravity)

### Tác vụ: Tích hợp Google Mail (Gmail SMTP) & Bổ sung email chào mừng khi đăng ký

#### A. Thay đổi về mặt tính năng & kiến trúc
1. **Chuyển đổi SMTP từ Mailpit sang Gmail:**
   * Cho phép cấu hình linh hoạt qua biến môi trường trong file `.env`. Nếu không khai báo Gmail credentials, hệ thống tự động fallback về Mailpit local.
   * Đã kiểm thử gửi email thật thành công qua Google SMTP (`smtp.gmail.com:587`, TLS) tới hộp thư của Scrum Master/Dev (`shellingofficial@gmail.com`).
2. **Luồng Đăng ký (PB05 / US-05):**
   * Sau khi tài khoản được tạo thành công, hệ thống tự động gửi `WelcomeMail` thông báo kích hoạt tài khoản và cung cấp link đăng nhập.
   * Gói bọc trong `try...catch` để việc gửi mail không làm gián đoạn response 201 nếu gặp lỗi mạng.
3. **Luồng Quên mật khẩu (PB07 / US-07):**
   * Giữ nguyên cơ chế bảo mật (token SHA-256 một lần, hạn 60 phút). Khi có yêu cầu, email gửi trực tiếp về Gmail thật của người dùng kèm link đặt lại mật khẩu.

#### B. Danh sách file thay đổi & tạo mới
* **Tạo mới:**
  * [`backend/app/Mail/WelcomeMail.php`](backend/app/Mail/WelcomeMail.php): Mailable class xử lý gửi mail chào mừng.
  * [`backend/resources/views/emails/welcome.blade.php`](backend/resources/views/emails/welcome.blade.php): Template Blade email tiếng Việt.
* **Chỉnh sửa:**
  * [`backend/config/mail.php`](backend/config/mail.php): Thêm cấu hình `'encryption' => env('MAIL_ENCRYPTION')`.
  * [`backend/app/Http/Controllers/AuthController.php`](backend/app/Http/Controllers/AuthController.php): Kích hoạt gửi `WelcomeMail` trong action `register`.
  * [`docker-compose.yml`](docker-compose.yml): Truyền động các biến môi trường `MAIL_*` từ `.env` vào container `api`.
  * [`backend/tests/Feature/SprintOneApiTest.php`](backend/tests/Feature/SprintOneApiTest.php): Bổ sung `Mail::fake()` và kiểm tra `Mail::assertSent(WelcomeMail::class)`.
  * [`.env.example`](.env.example) & [`backend/.env.example`](backend/.env.example): Bổ sung template cấu hình SMTP.
  * [`.env`](.env) (local): Đã nạp cấu hình Gmail thật (file này được `.gitignore` bảo vệ tuyệt đối).

### Tác vụ 2: Việt hóa toàn bộ giao diện Frontend & Chuẩn hóa trải nghiệm như sản phẩm thật

#### A. Thay đổi về mặt giao diện & trải nghiệm (UX/UI)
1. **Trang Đăng nhập (`/login`):**
   * Lược bỏ hoàn toàn các đoạn text ám chỉ demo (bỏ các ghi chú "Sprint 1 demo", "không phải MVP", v.v.).
   * Lược bỏ toàn bộ các nút chọn nhanh tài khoản demo (`.demo-accounts`, "Lecturer", "Student", "Administrator") và mật khẩu mặc định. Người dùng tự nhập tài khoản thủ công.
   * Để trống ô email và mật khẩu khi mới tải trang (sẵn sàng cho người dùng thật).
   * Việt hóa toàn bộ nhãn, thông báo, nút bấm, tiêu đề và thông điệp chào mừng.
2. **Các màn hình & Thành phần còn lại:**
   * Việt hóa 100% tất cả các màn hình: `RegisterView`, `PasswordRecoveryView`, `LecturerDashboardView`, `CourseManagementView`, `CourseDetailView`, `StudentDashboardView`, `AdminDashboardView`, `AccessUnavailableView`, `NotFoundView`.
   * Việt hóa các components: `AppSidebar`, `AppTopbar`, `AppModal`, `CourseCard`, `FileDropzone`, `StatusBadge`.
   * Định dạng ngày giờ chuẩn tiếng Việt (`vi-VN`) trong `formatters.js`.
   * Chuẩn hóa thẻ `<title>` và `<html lang="vi">` trong `index.html`.
   * Giữ nguyên toàn bộ cấu trúc tên biến, API contract, routes và logic backend nội bộ.
   * Đã build thành công bản production Vite và cập nhật container `flts-web-1`.

---

## 3. Hướng dẫn bàn giao cho Codex (Next Steps)

Khi **Codex** tiếp nhận công việc, hãy chú ý các định hướng tiếp theo:

1. **Về Git:**
   * Tính năng Google Mail SMTP đã được merge vào `main` (PR #4, `050691e`).
   * Các thay đổi Việt hóa giao diện đang được đưa lên qua nhánh `feature/vietnamese-ui-localization` và mở Pull Request vào `main`.
2. **Về Sprint 2 Backlog (Ưu tiên tiếp theo):**
   * **Task 1 (Queue Worker):** Hiện tại email gửi đồng bộ (sync) nên request API mất ~1-2s chờ Google SMTP. Bước tiếp theo nên cấu hình `queue-worker` trong `docker-compose.yml` (`php artisan queue:work`) và chuyển `Mail::to()->send()` sang `Mail::to()->queue()`. Queue này cũng sẽ dùng cho pipeline xử lý tài liệu Sprint 2.
   * **Task 2 (PB13 State Machine & Schema):** Tạo migration cho `document_processing_runs` (lưu các stage: `queued`, `extracting`, `cleaning`, `chunking`, `embedding`, `persisting`).
   * **Task 3 (PB15/PB16 Text Extraction):** Triển khai trích xuất văn bản trong `ai-service` bằng `pypdf` (PDF text-based) và `python-docx` (DOCX).

---

## 4. Codex verification & AI decision update — 02/10/2026

- Đã đọc toàn bộ handoff, master context, codebase guide và Sprint 2 execution plan; không triển khai feature/package Sprint 2 trong phiên xác minh này.
- Git sạch trên `main` tại `371ddca`; PR #4 và PR #5 đã merge.
- Đã xác minh source Gmail SMTP/`WelcomeMail`, fallback Mailpit, UI tiếng Việt và login không còn nút/text tài khoản demo.
- `docker compose ps` xác nhận `api`, `web`, `mysql`, `mailpit`, `ai` đều Up; MySQL và Mailpit healthy.
- PHPUnit trong API container pass **7 tests, 50 assertions**. Con số 49 trong snapshot cũ đã tăng một assertion do test `WelcomeMail` của PR #4.
- Quyết định mới của project lead: Google Gemini thay OpenAI cho Sprint 2 — embedding `text-embedding-004` (768 dimensions), generation `gemini-1.5-flash`; ChromaDB local là vector store chính thức của vertical slice.
- Không ghi Gemini API key vào tài liệu hoặc source. `PROJECT_CONTEXT.md` và `docs/SPRINT_2_EXECUTION_PLAN.md` đã được đồng bộ quyết định; package/image versions và các tham số còn TBD phải được pin khi bắt đầu branch triển khai.

## 5. Sprint 2 implementation handoff — 05/10/2026

- Branch in progress: `feature/sprint2-rag-vertical-slice`; do not merge or report Sprint 2 complete without PR/review and the evidence below.
- The 5-service snapshot is obsolete. Compose now has 7 services: `api`, `queue-worker`, `ai`, `chroma`, `mysql`, `mailpit`, `web`. `api` must be healthy before worker starts.
- Implemented code: Laravel queue/processing-runs/chunk/vector-reference migrations, authorized processing/retry/status/retrieval/evidence endpoints, FastAPI PDF/DOC/DOCX parser + cleaner/chunker + Chroma + Gemini SDK, and real Lecturer RAG controls in Course Detail. Legacy DOC uses `antiword` with a timeout; OCR is not implemented.
- Verified at this checkpoint: Docker services Up (API/AI/MySQL/Chroma/Mailpit healthy), Laravel test suite 15 tests / 80 assertions, FastAPI 9 tests. Laravel covers queue/retry/ownership, the multipart Laravel→FastAPI file contract, deletion-vs-processing protection, vector cleanup and safe public errors. The FastAPI suite covers a real local Chroma process/retrieve/course-filter/delete lifecycle with deterministic test vectors and rejects a missing service token; it does not claim Gemini verification. A missing key results in an explicit error; it is not a processed document.
- Security blocker: the Gemini key shared in chat must be revoked/rotated. Never copy it from chat. Put only the replacement in ignored `.env` (`GEMINI_API_KEY`); set a non-default `AI_SERVICE_TOKEN` too. Real embedding/generation E2E has not run until that is done.

## 6. Gemini API & Service Token verification — 05/10/2026 (Antigravity)

- **Cấu hình Secret Local:** Đã nạp thành công `AI_SERVICE_TOKEN` và `GEMINI_API_KEY` vào file local `.env` (được `.gitignore` bảo vệ).
- **Phát hiện & Sửa lỗi tương thích Gemini SDK:**
  1. Mô hình `text-embedding-004` bị lỗi 404 trên API v1beta của `google-genai` SDK. Đã chuyển sang `gemini-embedding-001` và cấu hình `output_dimensionality=768` để chuẩn hóa đúng vector 768 chiều theo kiến trúc của nhóm.
  2. Khắc phục lỗi đóng kết nối `RuntimeError: Cannot send a request, as the client has been closed` do SDK garbage-collect `Client` ẩn danh: đã gán biến tường minh `client = gemini_client()`.
  3. Mô hình sinh văn bản hoạt động ổn định với `gemini-2.5-flash`.
- **Trạng thái kiểm thử thực tế:**
  * Toàn bộ 7 services Docker đều đang chạy Up và Healthy (`api`, `ai`, `chroma`, `mysql`, `mailpit`, `queue-worker`, `web`).
  * Gọi trực tiếp Gemini Embedding tạo vector 768 chiều: **Thành công 100%**.
  * Gọi trực tiếp Gemini Generation với `gemini-2.5-flash`: **Thành công 100%**.
  * Chạy `pytest` trong container `ai`: **9/9 tests passed**.
  * Chạy `phpunit` trong container `api`: **15/15 tests, 80 assertions passed**.

## 7. RAG Processing Failure Diagnosis, Root Cause Fix & Full E2E Verification — 05/10/2026 (Antigravity)

### A. Nguyên nhân gốc rễ lỗi "Processing could not be completed" (HTTP 502 Bad Gateway)
1. **Payload quá lớn:** Ban đầu `embed(contents=contents)` gửi toàn bộ các chunks (ví dụ 294 chunks của file PDF 89 trang) trong **1 request duy nhất**. Gemini API từ chối payload lớn, dẫn đến HTTP 502 `EMBEDDING_FAILED`.
2. **Gemini Free Tier Quota:**
   - Quota embedding trên gói Free của Google là **100 requests / phút** và **1.000 requests / ngày** trên model `gemini-embedding-001`.
   - Quá trình test liên tục trước đó làm cạn kiệt quota ngày (1.000 requests) của `gemini-embedding-001`.
3. **Queue Worker timeout & thiếu cơ chế retry 429 ở AI service:**
   - Queue worker của Laravel có `backoff = 5` giây và `timeout = 180` giây. Khi Gemini trả về lỗi `429 RESOURCE_EXHAUSTED` (yêu cầu chờ 30s - 50s để reset hạn ngạch phút), FastAPI lập tức quăng lỗi 502 thay vì chờ và thử lại.
   - Worker thử lại 3 lần trong vòng 15 giây (vẫn trong khoảng thời gian quota bị khóa) khiến job bị đánh dấu `failed` và ghi nhận `Processing could not be completed`.

### B. Các giải pháp đã triển khai & tối ưu hóa mã nguồn
1. **Chuyển sang `gemini-embedding-2`:** Cập nhật `.env` và `.env.example` sang `GEMINI_EMBEDDING_MODEL=gemini-embedding-2` (hạn ngạch ngày còn nguyên vẹn, hỗ trợ native 768 dimensions).
2. **Batching & định dạng chuẩn `list[types.Content]`:**
   - Chia nhỏ dữ liệu thành từng batch 40 chunks (`batch_size = 40`).
   - Đóng gói mỗi chunk thành `types.Content(parts=[types.Part.from_text(text=item)])` để đảm bảo mỗi chunk trả về đúng 1 vector 768 chiều độc lập trong `resp.embeddings`.
3. **Cơ chế tự động backoff & retry thông minh cho 429:**
   - Viết hàm `extract_retry_delay(err_str)` tự động bóc tách thời gian chờ chính xác từ Gemini (`retry in Xs` hoặc `retryDelay`), giới hạn cận an toàn trong khoảng `[15s, 60s]`.
   - Thiết lập `max_retries = 15` cho mỗi batch, tự động sleep và tiếp tục xử lý khi quota reset thay vì throw exception.
4. **Nâng Timeout hệ thống lên 600 giây (10 phút):**
   - [`backend/app/Services/RagService.php`](backend/app/Services/RagService.php): Tăng `$this->client()->timeout(600)`.
   - [`backend/app/Jobs/ProcessTeachingDocument.php`](backend/app/Jobs/ProcessTeachingDocument.php): Tăng `public int $timeout = 600`.
   - [`docker-compose.yml`](docker-compose.yml): Cập nhật queue worker `--timeout=600`.
   - Đảm bảo các tài liệu PDF sách giáo trình dung lượng lớn (hàng trăm trang) có đủ thời gian chờ quota reset mà không bị worker kill.

### C. Kết quả kiểm thử End-to-End (E2E) thực tế
1. **Xử lý tài liệu Document 4 (`C1SE32-Proposal_FLTS_ver1.1.docx` — 46 chunks):**
   - Queue worker chạy thành công sau 6 giây.
   - `processing_status`: **`processed`** (Thành công).
2. **Xử lý tài liệu Document 5 (`[studocu.com] - Tập Bài Giảng Kinh Tế Chính Trị Mác - Lênin.pdf` — 89 trang, 294 chunks):**
   - AI service tự động chia 8 batches, tự động vượt qua các đợt rate limit (chờ 20s - 55s mỗi đợt) và hoàn tất sau 3 phút 35 giây.
   - `processing_status`: **`processed`** (Thành công).
   - Toàn bộ 294 vectors và chunks đã được lưu trữ an toàn trong ChromaDB và MySQL database (`flts_document_chunks`).
3. **Kiểm thử RAG Search (ChromaDB Retrieval):**
   - Query: `"kinh tế thị trường định hướng xã hội chủ nghĩa"`.
   - Trả về 3 chunks có độ tương đồng cực cao (score ~ 0.795), trích xuất chính xác nội dung từ tài liệu vừa nạp.
4. **Kiểm thử Grounded Evidence Generation (`gemini-2.5-flash`):**
   - Prompt: `"Nêu tính ưu việt của kinh tế thị trường định hướng xã hội chủ nghĩa"`.
   - Trả về câu trả lời tiếng Việt mạch lạc, kèm đầy đủ trích dẫn `citations` (vector IDs chính xác) và đánh giá giới hạn `limitations`.

## 8. Codex follow-up verification — 05/10/2026

- Xác minh độc lập runtime: cả 7 service đang chạy; health AI trả `gemini-embedding-2`, `gemini-2.5-flash`, ChromaDB và trạng thái Gemini configured.
- Hiện còn hai chứng cứ có thể đối chiếu trực tiếp: run 14/document 5 có 294 chunks + 294 vector references; run 15/document 2 có 257 chunks + 257 vector references. Chroma giữ vector có course/document metadata tương ứng.
- Source/config đã được đồng bộ để model fallback của Compose, Laravel và FastAPI đều là `gemini-embedding-2` / `gemini-2.5-flash`; batch size 40 và retry limit 15 được đưa thành biến môi trường. Bổ sung FastAPI regression test cho batching, vector order, retry 429 và lỗi provider an toàn.
- Re-run regression: Laravel **15 tests / 80 assertions**; FastAPI **13 passed** (một cảnh báo deprecation không chặn test). Chi tiết evidence/boundary ở `docs/SPRINT_2_E2E_EVIDENCE.md`.
- Không suy diễn rằng toàn Sprint 2 đã Done: document DOCX 4 trong handoff đã bị xóa nên cần chạy lại để có evidence DB/Chroma; cần capture mới UI retrieval/evidence, Chroma restart với document thật và fixture/authorization/error matrix.

## 9. Codex evidence-response repair verification — 05/10/2026

- Sửa nguyên nhân 502 sau khi khắc phục 504: Gemini không còn phải sinh vector ID. Service yêu cầu `citation_indexes`, kiểm tra index nằm trong tập source retrieve, rồi map phía server sang vector ID/metadata thật.
- Retrieval/context redact địa chỉ e-mail; page/paragraph/table locator còn đúng khi chunk overlap. Tương tác UI gặp quota Gemini trả 429 an toàn/thử lại thay vì chờ retry dài; Nginx có timeout RAG tương ứng.
- Kiểm chứng sau recreate app services: 7 service Compose running (API/AI/MySQL/Chroma/Mailpit healthy); real internal Gemini evidence request thành công và trả 2 citations. FastAPI: **16 passed** (1 warning không chặn); Laravel: **16 tests / 83 assertions**.
- Phần chưa đủ evidence vẫn giữ nguyên: DOCX fixture/run mới, real-document Chroma restart, cross-account/UI capture, corrupt/empty fixture matrix và PR/review/ceremony evidence.
