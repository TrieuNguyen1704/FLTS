# Trạng thái closeout Sprint 1 — 26/09/2026

> Đây là bản ghi tiếp tục công việc dựa trên source, Docker và test đã chạy. Nó **không** tự điền giờ `Actual`, không nhận công cho từng thành viên, và không tuyên bố Sprint 1 hoàn tất chỉ vì có code hoặc tài liệu kế hoạch.

## 1. Phạm vi và nguồn đối chiếu

- Sprint 1 trong workbook: 25/09/2026–08/10/2026, PB01–PB14.
- Acceptance criteria ưu tiên: User Story v1.1, đã được chép/đối chiếu trong `PROJECT_CONTEXT.md`.
- Source branch đang thực hiện closeout: `feature/sprint1-closeout`.
- Không chỉnh sửa Word/Excel gốc. Sheet `Actual` chưa phải bằng chứng giờ thực tế.

## 2. Trạng thái PB/US theo bằng chứng

| PB / US | Trạng thái | Bằng chứng hiện có | Việc còn lại / giới hạn |
|---|---|---|---|
| PB01 / US-01 — Git & cấu trúc | Phần lớn hoàn thành | GitHub remote, `main`, branch theo `feature/*`, PR và ruleset đã được tạo; `docs/DEVELOPMENT.md` ghi quy ước. | Cần từng thành viên clone/chạy và xác nhận quyền GitHub thật; không thay bằng claim. |
| PB02 / US-02 — Docker environment | Đã kiểm chứng trên máy hiện tại | `docker compose up --build -d`, `docker compose ps`: `api`, `ai`, `web`, `mysql`, `mailpit` đều Up/healthy; `.env.example` và README có lệnh chạy. | Cần bằng chứng chạy local của các máy thành viên nếu dùng để đóng team acceptance. |
| PB03 / US-03 — Coding/review standard | Đã có tài liệu | `docs/DEVELOPMENT.md`, PR checklist, quy tắc không commit `.env`, backend authorization. | Review độc lập chỉ là khuyến nghị theo ruleset hiện tại; cần thực hiện thật khi có thay đổi do thành viên khác review. |
| PB04 / US-04 — CI/test foundation | Đã có nền tảng, remote quality check còn chờ | GitHub Actions có `backend`, `frontend`; source thêm `backend-quality` PHP syntax lint. Docker PHPUnit pass 7 tests/49 assertions. | Push PR này để `backend-quality` chạy xanh rồi thêm nó vào required checks nếu nhóm muốn bắt buộc. |
| PB05 / US-05 — Registration | Đã triển khai và test | `/register`, `POST /api/auth/register`, validation backend; test đăng ký hợp lệ và dữ liệu sai pass. | Không cho self-register Admin là quyết định bảo mật cho demo. |
| PB06 / US-06 — Login/logout | Đã kiểm chứng | Login/Logout API, auth store/guard; Browser đã vào Lecturer, Admin, Student dashboard; PHPUnit pass. | Token random một tài khoản/một phiên là giới hạn demo, không phải auth production. |
| PB07 / US-07 — Password recovery | Đã triển khai và test | Reset token hash + expiry one hour + one-time use; `PasswordRecoveryView`; Mailpit local nhận reset email; PHPUnit pass. | Mailpit chỉ local; cần provider email/queue production nếu sau này deploy. |
| PB08 / US-08 — RBAC | Đã kiểm chứng | `auth.token`, `role:*`, ownership trong controller; Student/Admin/Lecturer UI routes có guards; PHPUnit kiểm tra unauthorized. | UI guard không thay thế backend, nên endpoint mới vẫn phải test server-side. |
| PB09 / US-09 — Account/role management | Đã triển khai và test | `/admin/dashboard`, `GET/PATCH /api/admin/users`; suspend revoke token; API chặn non-Admin/self-update/final active Admin removal; PHPUnit pass. | Không có profile/audit log/rate limit production. |
| PB10 / US-10 — Course management | Đã triển khai và test | Lecturer create/list/detail/update; UI modal Edit course; ownership kiểm tra tại API; PHPUnit tạo/sửa course pass. | Không có archive/delete course trong scope hiện tại. |
| PB11 / US-11 — Course access | Đã kiểm chứng | Enrollment schema/API cũ, `FLIP-101` seed cho Student; Student UI chỉ nhận course được backend trả; PHPUnit chặn Student không được cấp quyền. | Chưa có UI để Lecturer tự enroll arbitrary Student; đây là carry-over UI, không giả là hoàn chỉnh. |
| PB12 / US-12 — Document upload | Đã kiểm chứng | Lecturer upload PDF/DOC/DOCX, validation type/size, storage + metadata, ownership; PHPUnit pass; luồng upload/proxy đã được kiểm tra trước đó. | Không có antivirus, extraction hoặc content inspection. |
| PB13 / US-13 — Metadata/status | **Một phần, không đóng** | Tên/MIME/kích thước/thời gian/status `uploaded_pending_processing` hiển thị ở Course Detail. | Chưa có worker/extractor nên không có transition theo pipeline hoặc lỗi processing thật. Không được đánh dấu Done. Đây là PB13 Sprint 2 carry-over. |
| PB14 / US-14 — Document management | Đã triển khai và test quyền | List/metadata/delete có sẵn; bổ sung search theo tên, download action; API có ownership; test truy cập document của Lecturer khác bị chặn. | Chưa có test browser thực tế cho byte download; cần thêm khi có fixture upload ổn định. |

## 3. Bằng chứng lệnh đã chạy trong phiên closeout

| Lệnh / thao tác | Kết quả thực tế |
|---|---|
| `docker compose up --build -d` | Pass; build/recreate thành công, thêm service Mailpit. |
| `docker compose ps` | `flts-api-1`, `flts-ai-1`, `flts-web-1`, `flts-mysql-1`, `flts-mailpit-1` đều Up; MySQL/Mailpit healthy. |
| `docker compose exec -T api php vendor/bin/phpunit --testdox` | Pass: **7 tests, 49 assertions**. |
| `GET /api/health` | `{"status":"ok","service":"laravel-api"}`. |
| `GET http://localhost:8001/health` | FastAPI trả `ok` và nêu rõ chỉ là placeholder, extraction/RAG là Sprint 2. |
| Browser `http://localhost:8080` | Login UI hiển thị đúng; Lecturer chuyển `/lecturer/dashboard`; Admin chuyển `/admin/dashboard`; Student chuyển `/student/dashboard` và chỉ thấy `FLIP-101`. |
| `POST /api/auth/password-reset/request` + Mailpit API | Response chung hợp lệ; Mailpit ghi nhận 1 email reset local. |
| Frontend production build | Đã pass trong phiên này bằng `npm.cmd run build` (Vite 5.4.21). |
| PHP syntax scan | Đã pass cho `app`, `routes`, `config`, `database`, `tests`. |

Không có dòng nào ở trên là bằng chứng cho RAG, extract PDF/DOC/DOCX, chunking, embedding, vector database, quiz hoặc analytics.

## 4. File thay đổi của closeout này

### Backend và Docker

- `backend/app/Http/Controllers/AuthController.php` — password reset request/reset và kiểm tra account active khi login.
- `backend/app/Http/Controllers/AdminUserController.php` — list/search/update role/status có guard server-side.
- `backend/app/Http/Middleware/AuthenticateToken.php` — chặn/sử dụng token của account bị suspend.
- `backend/app/Http/Controllers/DocumentController.php` — tìm document theo tên.
- `backend/app/Mail/PasswordResetMail.php`, `backend/resources/views/emails/password-reset.blade.php`, `backend/config/mail.php` — email reset local.
- `backend/database/migrations/2026_09_26_000005_add_account_status_to_users_table.php` và `...000006_create_password_reset_tokens_table.php` — status account và token reset.
- `backend/routes/api.php`, `backend/app/Models/User.php` — route/fillable tương ứng.
- `docker-compose.yml`, `.env.example`, `backend/.env.example` — Mailpit và SMTP local.

### Frontend

- `frontend/src/views/RegisterView.vue`, `PasswordRecoveryView.vue`, `AdminDashboardView.vue` — màn hình thật cho Registration/Recovery/Admin.
- `frontend/src/views/LoginView.vue`, `CourseDetailView.vue`, `components/AppSidebar.vue`, `router/index.js`, `assets/main.css` — điều hướng, UI và course/document actions.
- `frontend/src/services/api.js`, `authService.js`, `courseService.js`, `documentService.js`, `adminService.js` — gom API calls ở service layer.

### Quality/documentation

- `.github/workflows/ci.yml` — thêm job PHP syntax lint.
- `backend/tests/Feature/SprintOneApiTest.php` — mở rộng coverage cho registration, reset, account management, update/search/document authorization.
- `README.md`, `docs/DEMO_2026-09-28.md`, `docs/CODEBASE_GUIDE_VI.md`, `docs/DEVELOPMENT.md`, `docs/SPRINT_2_EXECUTION_PLAN.md`, `PROJECT_CONTEXT.md` — đồng bộ cách chạy, scope và điểm handoff.
- `.gitignore` — bỏ qua file lock tạm `~$*.xlsx` của Excel, không xóa file Excel người dùng đang mở.
- `frontend/src/assets/main.css`, `frontend/src/components/AppSidebar.vue` — closeout visual fix: bảng Account Management thu gọn ở laptop hẹp, text banner được phép xuống dòng và icon sidebar dùng CSS thay vì ký tự bị lỗi encoding trên Windows.

## 5. Handoff bắt đầu Sprint 2

1. Mở PR từ `feature/sprint1-closeout`, để GitHub chạy `backend-quality`, `backend`, `frontend`; merge chỉ khi kiểm tra xanh.
2. Trước khi thay đổi RAG, ghi nhận PB13 là carry-over và chốt ChromaDB/version theo quyết định nhóm. OpenAI direction đã ghi trong context, nhưng chưa có API key/SDK trong repo.
3. Bắt đầu theo `docs/SPRINT_2_EXECUTION_PLAN.md`: processing state machine + queue thật, PDF extraction text-based, DOCX/DOC spike, cleaning, chunking, embedding/vector, authorized retrieval. Không nhảy thẳng sang UI RAG giả.
4. Dùng `.env` local cho secrets; không commit `OPENAI_API_KEY`. Chỉ bổ sung secret cùng với PB liên quan và test/error handling.

## 6. Lệnh kiểm tra lại trên Windows Docker Desktop

```powershell
# Tạo .env local từ file mẫu nếu máy chưa có; không commit file .env.
Copy-Item .env.example .env

# Build và chạy toàn bộ stack local ở chế độ nền.
docker compose up --build -d

# Xem trạng thái container; cần api, ai, web, mysql và mailpit là Up/healthy.
docker compose ps

# Chạy PHPUnit trong chính runtime Docker để tránh khác biệt PHP/extension của máy host.
docker compose exec -T api php vendor/bin/phpunit --testdox

# Kiểm tra Laravel API health.
Invoke-RestMethod http://localhost:8000/api/health

# Kiểm tra FastAPI placeholder health; đây không phải RAG pipeline.
Invoke-RestMethod http://localhost:8001/health
```

Mở `http://localhost:8080` để demo FLTS và `http://localhost:8025` để xem email reset local. Tài khoản seed và password nằm trong `README.md`.

## 7. Blocker / xác nhận cần từ nhóm

- **Không được gọi Sprint 1 100% Done** khi PB13/US-13 chưa có state transition/error thật, hoặc khi nhóm chưa xác minh clone/run trên các máy khác theo acceptance team environment.
- Sheet `Actual` và bằng chứng review từng người thuộc trách nhiệm nhóm; tài liệu này không tự điền chúng.
- Cần push/PR branch này để có evidence cho job CI mới và lựa chọn nó trong GitHub ruleset nếu muốn required.
- ChromaDB/local vector decision vẫn cần xác nhận rõ trước PB20. Các file source không chứa OpenAI API key, SDK hay Chroma service.
