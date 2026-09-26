# Hướng dẫn đọc codebase FLTS (Sprint 1)

> Cập nhật theo source hiện có ngày 26/09/2026. Đây là hướng dẫn cho **bản demo tiến độ Sprint 1**, không khẳng định MVP, RAG hay pipeline AI đã hoàn thành.

## 1. Bức tranh ngắn gọn

FLTS gồm bốn service chạy bằng Docker Compose:

```text
Browser
  -> web (Nginx, Vue 3 build) -- /api proxy --> api (Laravel/PHP)
                                                    -> mysql (MySQL 8.4)
  -> ai (FastAPI /health placeholder, chưa được Laravel hay Vue gọi)
```

Luồng Sprint 1 thực tế là: Lecturer/Student đăng nhập, Vue lưu bearer token, Laravel xác thực token và role, Lecturer tạo course hoặc tải tệp PDF/DOC/DOCX. Tệp và metadata được lưu; `uploaded_pending_processing` **không** có nghĩa là tệp đã được extraction, chunking, embedding hay RAG xử lý.

## 2. Cây thư mục và file quan trọng

```text
FLTS/
├── .env.example                    # Mẫu port, MySQL và giới hạn upload
├── docker-compose.yml              # Ghép mysql, api, ai, web và Docker volumes
├── README.md                       # Hướng dẫn chạy demo nhanh
├── PROJECT_CONTEXT.md              # Bối cảnh/ranh giới dự án đã được nhóm ghi nhận
├── frontend/
│   ├── Dockerfile                  # Build Vite rồi phục vụ dist bằng Nginx
│   ├── nginx.conf                  # SPA fallback và proxy /api sang Laravel
│   ├── package.json, vite.config.js
│   └── src/
│       ├── main.js, App.vue        # Điểm khởi động Vue và chọn layout
│       ├── router/index.js          # Route và navigation guard
│       ├── stores/auth.js           # Token, user, role, khôi phục/đăng xuất session
│       ├── services/                # api.js, authService.js, courseService.js, documentService.js
│       ├── views/                   # Login, Lecturer/Student dashboard, course pages
│       ├── components/              # Input/button/modal/toast/sidebar/dropzone/trạng thái dùng lại
│       ├── layouts/AppShell.vue     # Khung sidebar + topbar khi đã đăng nhập
│       └── assets/main.css          # CSS giao diện tự viết cho demo
├── backend/
│   ├── Dockerfile                   # PHP extensions, upload limit, migrate/seed khi start
│   ├── routes/api.php               # Toàn bộ API Sprint 1
│   ├── app/Http/Controllers/        # AuthController, CourseController, DocumentController
│   ├── app/Http/Middleware/         # AuthenticateToken, EnsureRole
│   ├── app/Models/                  # User, Course, TeachingDocument và quan hệ Eloquent
│   ├── database/migrations/         # Schema users, courses, course_enrollments, teaching_documents
│   ├── database/seeders/DatabaseSeeder.php
│   ├── tests/Feature/SprintOneApiTest.php
│   ├── bootstrap/app.php            # Alias middleware và cấu hình exception API JSON
│   ├── config/                      # Cấu hình Laravel phần lớn là framework scaffold
│   └── composer.json, phpunit.xml   # Package/backend test config
├── ai-service/
│   ├── main.py                      # Chỉ có GET /health, trả rõ placeholder
│   ├── requirements.txt
│   └── Dockerfile
└── docs/
    ├── DEMO_2026-09-28.md           # Kịch bản demo và bằng chứng chạy
    ├── DEVELOPMENT.md                # Ghi chú phát triển
    ├── CODEBASE_GUIDE_VI.md          # File hiện tại
    └── reference/                    # Word/Excel nguồn tham chiếu: US v1.1, Sprint 1, plan/proposal
```

`docs/reference/` là tài liệu nguồn, không phải source chạy. Khi chi tiết xung đột, ưu tiên User Story v1.1 và Sprint 1 mới nhất, nhưng không biến một mục TBD thành quyết định kỹ thuật đã chốt.

## 3. Phân loại: cái gì tự sinh, cái gì nhóm cần hiểu

| Nhóm | Ví dụ trong repo | Cách hiểu |
|---|---|---|
| Dependency/tự sinh, không sửa/comment | `frontend/node_modules/`, `backend/vendor/`, `frontend/dist/`, `package-lock.json`, `composer.lock` | Code package/build output. Tạo lại bằng install/build; không phải logic Sprint 1. |
| Runtime cache/log/volume | `backend/storage/framework/`, `backend/storage/logs/`, `backend/bootstrap/cache/`, Docker volumes `mysql_data`, `document_storage` | Laravel/Docker tạo khi chạy. Không commit hoặc giải thích như code nghiệp vụ. Xóa volume sẽ mất dữ liệu demo. |
| Scaffold/boilerplate có thể cấu hình | `backend/artisan`, `backend/public/index.php`, phần lớn `backend/config/`, `frontend/index.html`, `frontend/main.js`, `vite.config.js`, Dockerfile khung base image | Do Laravel/Vite/Docker tạo hoặc theo convention. Cần hiểu mục đích và chỉ sửa khi cần cấu hình dự án. `bootstrap/app.php` là scaffold đã được nhóm chỉnh để đăng ký middleware. |
| Manifest/cấu hình nhóm kiểm soát | `.env.example`, `docker-compose.yml`, `backend/.env.example`, `composer.json`, `frontend/package.json`, `frontend/nginx.conf`, Dockerfile | Quyết định cách chạy local/build, package trực tiếp và giới hạn upload/proxy. Không chứa business flow nhưng rất quan trọng để demo chạy. |
| Code Sprint 1 nhóm cần đọc kỹ | `frontend/src/**`, Laravel route/controller/middleware/model/migration/seeder/test, `ai-service/main.py`, docs | Đây là nơi hiện thực login/RBAC/course/upload và ranh giới “chưa làm”. |

Lưu ý: “boilerplate” không đồng nghĩa “không quan trọng”. Ví dụ `frontend/nginx.conf` quyết định `/api` có tới API được hay không, và `phpunit.xml`/`CreatesApplication.php` quyết định test có cô lập dữ liệu Docker hay không.

## 4. Thứ tự đọc code cho người mới

1. Đọc `README.md`, `PROJECT_CONTEXT.md`, `docs/DEMO_2026-09-28.md` để biết phạm vi thật và lệnh chạy.
2. Đọc `.env.example`, `docker-compose.yml`, `frontend/nginx.conf`, hai Dockerfile để hiểu bốn service, port và volume.
3. Bắt đầu UI ở `frontend/src/router/index.js`, rồi `stores/auth.js`, sau đó `services/api.js` và ba service nghiệp vụ.
4. Đọc view tương ứng (`LoginView.vue`, `CourseManagementView.vue`, `CourseDetailView.vue`, dashboard) và các component tái sử dụng, nhất là `FileDropzone.vue`.
5. Đi theo request sang `backend/routes/api.php`, middleware, controller, model và migration.
6. Đọc `DatabaseSeeder.php` để biết dữ liệu demo được bảo đảm bởi source, rồi `SprintOneApiTest.php` để thấy các acceptance behavior đã có kiểm tra.
7. Sau cùng đọc `ai-service/main.py`: nó chỉ xác nhận service placeholder sống, không phải pipeline AI.

## 5. Frontend Vue 3 + Vite

- `main.js` mount `App.vue`; `App.vue` dùng `RouterView` và chỉ bọc route cần đăng nhập trong `layouts/AppShell.vue`.
- `router/index.js` khai báo các route Lecturer, Student, Login, 404. `beforeEach` gọi `authStore.initialize()` để xác minh token đã lưu qua `/auth/me`, redirect khách sang Login, và đưa role sai về dashboard thích hợp. Đây chỉ là điều hướng UI; quyền thật luôn được Laravel kiểm tra.
- `stores/auth.js` là singleton reactive giữ `token`, `user`, `role`; token/user được lưu localStorage (`flts_token`, `flts_user`). `logout()` gọi API rồi luôn dọn local state để client không kẹt nếu token đã hết hạn.
- `services/api.js` là một cổng `fetch` duy nhất: thêm `Authorization: Bearer ...`, chỉ đặt JSON content type khi body không phải `FormData`, chuẩn hóa lỗi thành `ApiError`. Các service khác không tự gọi URL rải rác: `authService.login`, `courseService.create/list/get`, `documentService.upload/list/remove` đều đi qua đây.
- `components/` chứa phần dùng lại thay vì sao chép UI: `BaseButton`, `BaseInput`, `AppModal`, `AppToast`, `AppState`, `AppSidebar`, `AppTopbar`, `CourseCard`, `FileDropzone`, `StatusBadge`. CSS tự viết nằm ở `assets/main.css`; không có UI framework nặng.

## 6. Luồng dữ liệu thực tế

### 6.1 Đăng nhập và RBAC

1. `frontend/src/views/LoginView.vue` gửi form `{ email, password }` bằng `authStore.login(form)`.
2. `frontend/src/stores/auth.js` gọi `frontend/src/services/authService.js`; service gọi `apiRequest('/auth/login', { method: 'POST', body: JSON.stringify(...) })`.
3. Nginx (`frontend/nginx.conf`) proxy `/api/...` đến `api:8000/api/...`. Laravel map `POST /api/auth/login` tại `backend/routes/api.php` vào `AuthController@login`.
4. `AuthController@login` dùng `$request->validate()` yêu cầu `email` hợp lệ và `password` string. Nó lower-case email, `Hash::check()` mật khẩu đã hash trong bảng `users`, tạo raw token ngẫu nhiên 64 ký tự và chỉ lưu SHA-256 (`api_token_hash`). Sai credentials hiện trả `422`; thiếu/sai format cũng do Laravel validation trả `422` với `errors`.
5. Response `{ token, user }` quay về auth store, được lưu localStorage. `router.push()` đưa Lecturer vào `lecturer-dashboard` hoặc Student vào `student-dashboard`.
6. Với API đã đăng nhập, `AuthenticateToken` đọc Bearer token, hash token để tìm user rồi đặt `$request->user()`. `EnsureRole` chỉ cho các role route cho phép. `CourseController`/`DocumentController` còn kiểm tra owner/enrollment ở resource level; không tin việc Vue đã ẩn nút.
7. `POST /api/auth/logout` trong `AuthController@logout` xóa `api_token_hash`. Vì schema chỉ có một hash, một lần login mới cũng thay token cũ: đây là lựa chọn demo tối thiểu, không phải session management production.

### 6.2 Course

- Lecturer: `CourseManagementView.vue` validate form cơ bản, gọi `courseService.create()` -> `POST /api/courses`. Route có `auth.token` và `role:lecturer`; `CourseController@store` validate `name` (tối đa 160), `code` (50), `description` (2000) và tạo qua `$request->user()->courses()` nên `lecturer_id` không do client quyết định.
- Danh sách: `CourseController@index` trả course do Lecturer sở hữu; Student chỉ nhận course có record pivot `course_enrollments`; Admin hiện nhận tất cả. `StudentDashboardView.vue` chỉ hiển thị response này.
- Chi tiết: `CourseController@show` gọi `ensureCanAccess()`: Admin, Lecturer sở hữu, hoặc Student đã được enroll mới qua. `POST /api/courses/{course}/enrollments` cần Lecturer và `ensureOwner()`, rồi `syncWithoutDetaching()` để cấp quyền lặp lại không tạo bản ghi trùng.

### 6.3 Upload document

1. `CourseDetailView.vue` nhận file từ `FileDropzone.vue`. Client chỉ kiểm tra phần mở rộng PDF/DOC/DOCX và 10 MB để phản hồi sớm; đây không phải hàng rào bảo mật.
2. `documentService.upload()` tạo `FormData` với đúng field `document`; `apiRequest()` không gán JSON content type để browser tạo multipart boundary.
3. Route `POST /api/courses/{course}/documents` yêu cầu token + Lecturer. `DocumentController@store` lại gọi `ensureOwner()`, lấy `DOCUMENT_MAX_KB` (mặc định 10240), Laravel validate `required|file|mimes:pdf,doc,docx|max:...`.
4. Laravel sinh UUID, lưu binary vào local disk tại `storage/app/documents/{course_id}/...`; Docker volume `document_storage` giúp giữ file khi rebuild image. Bảng `teaching_documents` lưu original name, mime, extension, size, uploader và path.
5. Status ghi cứng là `uploaded_pending_processing`. UI `StatusBadge.vue` hiện “Pending processing”; sau upload, `loadCourse()` lấy lại metadata thật từ API. Không worker nào gọi `ai-service` hoặc thay status sang `processing`/`processed` trong source Sprint 1.

## 7. Database hiện có

| Bảng | Cột/ý nghĩa chính | Quan hệ |
|---|---|---|
| `users` | name, email unique, password hash, role (`lecturer`/`student`/`admin`), `api_token_hash` nullable unique | Lecturer `hasMany` courses; Student `belongsToMany` courses qua enrollment; user upload document. |
| `courses` | `lecturer_id`, name, code, description | `belongsTo` lecturer; `belongsToMany` students; `hasMany` documents. Xóa lecturer sẽ cascade course. |
| `course_enrollments` | course_id, student_id, timestamps, unique pair | Pivot cấp quyền Student xem course. Hai foreign key cascade. |
| `teaching_documents` | course_id, uploaded_by, original/stored name, mime, extension, size, status, error | `belongsTo` course. Tên schema có state `processing`/`processed`/`failed` để dành, nhưng code hiện chỉ tạo pending. |

## 8. Lệnh vận hành và tác động dữ liệu

| Lệnh (PowerShell, root repo) | Làm gì | Tạo/sửa dữ liệu? |
|---|---|---|
| `Copy-Item .env.example .env` | Tạo cấu hình local từ mẫu. Chỉ làm lần đầu hoặc khi muốn reset config. | Tạo `.env`; không tạo DB. |
| `docker compose up --build -d` | Build image nếu cần và khởi động bốn service. API command chạy migration rồi seeder trước khi serve. | Tạo/cập nhật schema và seed idempotent; giữ volume cũ. |
| `docker compose ps` | Xem trạng thái container. | Không. |
| `docker compose logs api` | Xem startup/migration/error Laravel. | Không. |
| `docker compose exec api php vendor/bin/phpunit` | Chạy feature test Laravel. `CreatesApplication.php` ép SQLite in-memory cho test. | Chỉ tạo dữ liệu test tạm trong memory; không xóa user/course MySQL demo. |
| `docker compose exec api php artisan migrate --force` | Chạy migration đang thiếu trong MySQL container. Bình thường API startup đã làm việc này. | Có thể tạo/đổi schema. |
| `docker compose exec api php artisan db:seed --force` | Chạy `DatabaseSeeder`. | Bảo đảm ba account và FLIP-101 enrollment; `firstOrCreate`/`syncWithoutDetaching` không nhân bản seed. |
| `docker compose build --no-cache` | Build lại image không dùng cache. | Không sửa DB/volume, nhưng tốn thời gian. |
| `docker compose down` | Dừng/xóa container và network. | Giữ `mysql_data` và `document_storage`. |
| `docker compose down -v` | Dừng và xóa cả volumes. | **Xóa không khôi phục** database và file upload demo. Chạy `up` sau đó sẽ seed lại dữ liệu cơ bản. |
| `Set-Location frontend; npm run build` | Kiểm tra Vite compile production tại local. | Tạo/ghi đè `frontend/dist/` (build artifact). |

Tài khoản được source seeder đảm bảo: `lecturer@flts.test`, `student@flts.test`, `admin@flts.test`, đều có password `DemoPass123!`. Seeder cũng gán Student vào course `FLIP-101`. Course/tệp do người demo tạo có thể còn trong Docker volume từ lần chạy trước.

## 9. Chưa có trong repository hiện tại

- PDF/DOC/DOCX text extraction, OCR, virus scan hoặc background job.
- Chunking, embedding model, vector database, retrieval hoặc bất kỳ LLM provider nào.
- RAG chat/answer generation, Quiz generation, publish workflow cho tài liệu/course.
- Student xem/tải document; endpoint document Sprint 1 đang Lecturer-only.
- UI để Lecturer chọn/enroll arbitrary Student (API cấp quyền đã có, seeder dùng nó ở mức database); quản lý account/profile/password reset.
- Analytics, learning progress, statistics, production-grade multi-device session, rate limiting/audit logging.

Các status DB tương lai và service FastAPI không phải bằng chứng các tính năng trên đã chạy. Provider LLM, embedding model và vector database vẫn TBD.

## 10. Câu hỏi thường gặp khi demo

| Câu hỏi | Trả lời trung thực dựa trên repo |
|---|---|
| “Đây có phải MVP hoàn chỉnh không?” | Không. Đây là demo tiến độ Sprint 1: account/RBAC, course và lưu file/metadata. RAG/Quiz/analytics chưa làm. |
| “Khi upload PDF, hệ thống đã đọc nội dung chưa?” | Chưa. `DocumentController@store` lưu file + metadata với `uploaded_pending_processing`; không có extractor/worker nào được gọi. |
| “AI service đang làm gì?” | `ai-service/main.py` chỉ expose `/health` và tự trả `placeholder only`; Laravel/Vue chưa gọi nó. |
| “Student có thấy tất cả course không?” | Không. `CourseController@index` truy vấn `course_enrollments`, còn `ensureCanAccess()` chặn Student chưa được cấp quyền kể cả khi biết URL. |
| “Chỉ ẩn nút upload có đủ bảo mật không?” | Không. Route có `auth.token`, `role:lecturer`; controller còn đối chiếu `course.lecturer_id` với user đang đăng nhập. |
| “Tại sao dùng token chứ không dùng Laravel Sanctum?” | Source hiện dùng token random tự quản lý, hash trong `users`, một token/account cho demo. Đây là giới hạn đã ghi rõ, chưa phải giải pháp production. |
| “File nằm ở đâu?” | Laravel local storage `storage/app/documents/{course_id}`; Compose mount volume `document_storage` để file tồn tại qua rebuild container. |
| “Có test gì?” | `SprintOneApiTest.php` kiểm tra login/logout, Student bị cấm, Lecturer tạo/cấp quyền course, upload owner/định dạng/kích thước/delete. Chạy lệnh PHPUnit trước demo để có bằng chứng của máy hiện tại. |
| “Vì sao status schema có processed mà UI luôn Pending?” | Schema dự phòng contract cho pipeline sau; Sprint 1 controller chỉ ghi `uploaded_pending_processing`, nên không được trình bày như đã xử lý. |
| “Làm sao reset demo?” | `docker compose down -v` xóa dữ liệu; sau đó `docker compose up --build -d` chạy migration + seeder. Cần cảnh báo vì thao tác xóa tất cả upload/course local. |

## 11. Điểm cần giữ khi phát triển tiếp

- Giữ service layer frontend: không đưa `fetch()` trực tiếp trở lại nhiều component.
- Bất kỳ endpoint mới nào cũng cần backend authorization và test độc lập; Vue guard không thay thế authorization.
- Nếu thêm extraction/RAG, cần chọn có chủ đích queue/worker, file scanning, model/provider, embedding/vector DB, retry/error state và quyền Student trước khi đổi status document.
- Cập nhật `README.md`, `PROJECT_CONTEXT.md`, demo script và tests cùng với thay đổi behavior; không điền trạng thái hoàn thành chỉ vì đã có UI hoặc enum schema.
