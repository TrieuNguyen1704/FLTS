# FLTS Sprint 1 Demo

Đây là bản demo tiến độ Sprint 1, không phải MVP hoàn chỉnh. Luồng đã có: đăng ký, đăng nhập/đăng xuất token, RBAC Lecturer/Student/Admin, Lecturer tạo/xem khóa học, Student chỉ xem khóa học đã được cấp quyền, và Lecturer tải PDF/DOC/DOCX để lưu metadata. Không có trích xuất nội dung, embedding, vector database, RAG, Quiz hay thống kê.

Xem [hướng dẫn đọc toàn bộ codebase bằng tiếng Việt](docs/CODEBASE_GUIDE_VI.md) để phân biệt code framework/dependency với code Sprint 1, và để lần theo các luồng login, phân quyền, course và upload.

## Yêu cầu

- Windows 10/11 với Docker Desktop đang chạy và Docker Compose v2.
- Cổng `8080`, `8000`, `8001`, `3306` còn trống (có thể đổi bằng `.env`).

## Chạy demo

```powershell
Copy-Item .env.example .env
docker compose up --build -d
docker compose ps
```

Chờ trạng thái `api`, `ai`, `web`, `mysql` là running, rồi mở `http://localhost:8080`.

Tài khoản seed:

| Vai trò | Email | Mật khẩu |
|---|---|---|
| Lecturer | `lecturer@flts.test` | `DemoPass123!` |
| Student | `student@flts.test` | `DemoPass123!` |
| Administrator | `admin@flts.test` | `DemoPass123!` |

Khóa học `FLIP-101` đã được cấp quyền cho `student@flts.test`.

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
