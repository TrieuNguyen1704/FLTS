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
