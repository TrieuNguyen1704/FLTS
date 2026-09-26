# FLTS Sprint 1 Demo

Đây là bản demo tiến độ Sprint 1, không phải MVP hoàn chỉnh. Luồng đã có: đăng ký, đăng nhập/đăng xuất token, RBAC Lecturer/Student/Admin, Lecturer tạo/xem khóa học, Student chỉ xem khóa học đã được cấp quyền, và Lecturer tải PDF/DOC/DOCX để lưu metadata. Không có trích xuất nội dung, embedding, vector database, RAG, Quiz hay thống kê.

Xem [hướng dẫn đọc toàn bộ codebase bằng tiếng Việt](docs/CODEBASE_GUIDE_VI.md) để phân biệt code framework/dependency với code Sprint 1, và để lần theo các luồng login, phân quyền, course và upload.

Trạng thái closeout có bằng chứng thực tế, giới hạn còn lại và điểm bắt đầu Sprint 2: [docs/SPRINT_1_CLOSEOUT_STATUS.md](docs/SPRINT_1_CLOSEOUT_STATUS.md).

## Yêu cầu

- Windows 10/11 với Docker Desktop đang chạy và Docker Compose v2.
- Cổng `8080`, `8000`, `8001`, `8025`, `3306` còn trống (có thể đổi bằng `.env`).

## Chạy demo

```powershell
Copy-Item .env.example .env
docker compose up --build -d
docker compose ps
```

Chờ trạng thái `api`, `ai`, `web`, `mysql`, `mailpit` là running/healthy, rồi mở `http://localhost:8080`. Mailpit chỉ dùng cho email reset mật khẩu khi demo local tại `http://localhost:8025`.

Tài khoản seed:

| Vai trò | Email | Mật khẩu |
|---|---|---|
| Lecturer | `lecturer@flts.test` | `DemoPass123!` |
| Student | `student@flts.test` | `DemoPass123!` |
| Administrator | `admin@flts.test` | `DemoPass123!` |

Khóa học `FLIP-101` đã được cấp quyền cho `student@flts.test`.

## Bổ sung Sprint 1 đã kiểm chứng (26/09/2026)

- Người dùng có thể tự đăng ký **Lecturer** hoặc **Student**; Admin được seed hoặc được Admin khác gán role, không thể tự đăng ký role Admin.
- Password recovery tạo token một lần, hết hạn sau một giờ. Với Docker local, email được xem trong Mailpit, không gửi ra Internet.
- Admin có Account Management để tìm danh sách, đổi role/status của tài khoản khác. Suspend thu hồi token đang hoạt động; Admin không thể tự hạ role/tự suspend.
- Lecturer có thể sửa course, tìm kiếm document theo tên và tải xuống document của course mình sở hữu.
- `uploaded_pending_processing` vẫn chỉ có nghĩa file đã lưu; không có extraction, error state thật, embedding, vector DB hay RAG.

## API và kiểm tra nhanh

```powershell
Invoke-RestMethod http://localhost:8000/api/health
Invoke-RestMethod http://localhost:8001/health
docker compose exec api php vendor/bin/phpunit
```

Laravel API ở `http://localhost:8000/api`; giao diện Vue ở `http://localhost:8080`. Giao diện proxy `/api` vào Laravel nên không cần cấu hình CORS cho demo local.

## Dừng và reset

```powershell
docker compose down
```

Lệnh trên giữ dữ liệu MySQL và tệp đã tải. Để xóa hoàn toàn dữ liệu demo (không thể khôi phục bằng Docker), dùng:

```powershell
docker compose down -v
```

Sau reset, chạy lại `docker compose up --build -d`; migration và seeder sẽ tạo lại tài khoản/khoá học demo.

## Giới hạn đã biết

- Upload giới hạn 10 MB mặc định (`DOCUMENT_MAX_KB=10240` trong `.env`), chỉ chấp nhận phần mở rộng PDF/DOC/DOCX.
- Trạng thái `uploaded_pending_processing` nghĩa là tệp đã lưu nhưng **chưa** được trích xuất, chunk, embedding hoặc RAG xử lý.
- Nhà cung cấp LLM, embedding model và vector database vẫn TBD; demo không chọn thay nhóm.
- API dùng bearer token có hiệu lực đến đăng xuất hoặc lần đăng nhập mới của cùng tài khoản. Đây là lựa chọn tối thiểu cho demo, không phải cơ chế production.

## Cấu trúc

- `frontend/` — Vue 3 + Vite, build thành Nginx static site.
- `backend/` — Laravel API, migration/seed/test cho Sprint 1.
- `ai-service/` — FastAPI health placeholder; pipeline AI thuộc Sprint 2.
- `docs/DEMO_2026-09-28.md` — kịch bản và bằng chứng demo.
