# Phần việc Vương — quản lý khóa học và upload tài liệu

Ngày: 10/10/2026. Nhánh: `feature/sprint1-vuong-courses-documents`.

## Đối chiếu yêu cầu trong ba ảnh giao việc

| Yêu cầu | Thực hiện |
|---|---|
| Ba migration `2026_09_26_000002/000003/000004` | Có sẵn trên main; giữ nguyên migration đã triển khai. |
| `courses.code` unique | Bổ sung validation tạo/sửa và migration mới `2026_10_10_000023`. Migration dừng nếu dữ liệu hiện tại có mã trùng; không tự sửa dữ liệu. |
| Enrollment `course_id`, `student_id` | Có sẵn FK, unique cặp và timestamps. |
| `Course`, `TeachingDocument`, `User.courses()` | Các quan hệ đã có sẵn. |
| API tạo/list/show khóa học | Có sẵn, lecturer chỉ nhìn thấy khóa của mình. Bổ sung Form Request cho tạo/sửa. |
| Upload PDF/DOC/DOCX tối đa 10 MB | Form Request kiểm tra MIME và đuôi tên file; ownership được kiểm tra trước khi lưu. |
| Lưu dưới `storage/app/documents`, trạng thái pending | Có sẵn; bổ sung xử lý lỗi ghi ổ đĩa và dọn file nếu lưu DB thất bại. UUID tránh ghi đè file cùng tên. |
| Dropzone có tên, dung lượng và nút tải lên | Bổ sung bước chọn/xem trước/bấm tải lên; file sai làm mất lựa chọn cũ để tránh tải nhầm. |
| `CourseAndDocumentTest.php` | Bổ sung test chuyên biệt, dùng storage fake và SQLite in-memory; không gọi cloud AI. |
| Git branch/commit/push | Dùng nhánh và thông tin tác giả theo ảnh; không merge vào main. |

## Quyết định tương thích với mã nhóm hiện tại

Không đổi các cột đang được frontend, queue và RAG dùng: `stored_path` tương ứng `file_path` trong ảnh, `size_bytes` tương ứng `file_size`. Tên file lưu trên HDD là basename UUID của `stored_path`; tên người dùng là `original_name`. Không tạo các cột trùng lặp `file_name/file_path/file_size` chỉ để đổi cách gọi, vì ảnh có phần mẫu trong khi main đã có hợp đồng đang sử dụng.

API hiện tại vẫn là `/api` với `{course}`, `{courses}`, `{document}`, `{documents}`. Thay toàn bộ sang `/api/v1` và envelope Project Bible cần một task phối hợp riêng; commit này giữ tương thích Sprint 2/3. Upload không tự khởi chạy AI; luồng xử lý bất đồng bộ hiện có tiếp tục được giảng viên khởi chạy riêng.

Thêm chỉ mục `(lecturer_id, created_at, id)` cho khóa học và `(course_id, created_at, id)` cho tài liệu. Index B-tree này không tăng tốc tìm tên theo `%keyword%`. Các endpoint list hiện vẫn dùng `get()`; pagination và việc dashboard gọi nhiều API theo từng khóa học còn là điểm tối ưu chưa giải quyết trong phạm vi ảnh giao việc.

## Kiểm tra khi tích hợp

Chạy test riêng theo ảnh:

```powershell
docker compose exec -T api php vendor/bin/phpunit tests/Feature/CourseAndDocumentTest.php
```

Nếu Docker chưa chạy, kiểm thử native an toàn trên PHP có PDO SQLite:

```powershell
cd backend
php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/phpunit
cd ../frontend
npm run build
```

Trước khi áp dụng migration lên MySQL thật, kiểm tra mã khóa học trùng. Không dùng `migrate:fresh` trên dữ liệu nhóm. Không khởi chạy Chroma/LLM local để kiểm tra phần upload.

Bản kiểm tra ban đầu chạy native khi Docker daemon chưa hoạt động. Bằng chứng Docker/MySQL và browser bổ sung nằm ở mục dưới.

## Kết quả đã chạy

- Cài dependency đúng lockfile; không thay phiên bản dependency.
- Toàn bộ PHPUnit: **46 tests, 283 assertions**, passed; SQLite in-memory, storage fake trong test mới, 54 MB bộ nhớ báo bởi PHPUnit.
- `npm run build`: passed, 73 modules.
- Docker Compose không kết nối được `dockerDesktopLinuxEngine`; không chạy migration trên MySQL thật và không khởi động AI/vector service local.
- `git diff --check`: passed.

Giải thích bình dân: Form Request là cửa kiểm tra trước khi dữ liệu vào controller. Mã môn có khóa chống trùng trong database. Tài liệu được đổi tên ngẫu nhiên khi lưu để hai file cùng tên không đè lên nhau; nếu ghi database thất bại, file vừa tải sẽ được dọn. Khung kéo thả nay cho xem tên và dung lượng trước khi gửi.

## Xác minh trực tiếp Docker, MySQL và browser — 10/10/2026

- Docker Desktop/Engine đã khởi động được, Engine **29.1.3**. File socket Windows cũ làm Desktop lỗi startup; giữ nguyên bản sao thư mục runtime socket trước khi tạo lại. Không factory reset, prune, xóa volume hay đổi dữ liệu dự án.
- Chạy `docker compose up --build -d mysql mailpit api web`; MySQL và API healthy. Không khởi động AI, Chroma hoặc queue worker trong task kiểm tra upload.
- Lệnh đúng trong ảnh `docker compose exec -T api php vendor/bin/phpunit tests/Feature/CourseAndDocumentTest.php`: **10 tests, 55 assertions passed**, PHP 8.4.26.
- Toàn bộ PHPUnit trong API container: **46 tests, 283 assertions passed**, 52.50 MB. PHPUnit dùng SQLite in-memory để không xóa dữ liệu demo.
- Kiểm tra riêng MySQL thật: cả 23 migration có trạng thái Ran; index chống trùng mã môn và hai composite index mới hiện trong `SHOW INDEX`.
- `http://localhost:8080/api/health`: HTTP 200, `status=ok` qua Nginx proxy.
- Browser: tạo khóa `VUONG-SMOKE-1010`, ID 2; trang chi tiết mở thành công. Chọn `upload-check.pdf` hiển thị tên, 235 B và nút tải lên trước khi gửi. Bấm tải lên thành công, bảng hiển thị MIME `application/pdf`, dung lượng và `Chờ xử lý`.
- MySQL xác nhận document ID 1 thuộc khóa ID 2, `uploaded_pending_processing`; file thực tồn tại trên disk local của container.
- Browser chọn file thử `.exe` vô hại: thông báo từ chối PDF/DOC/DOCX, không xuất hiện nút tải lên. Các ca quá dung lượng được xác minh bởi PHPUnit; chưa thử file 10 MB qua browser hoặc thao tác kéo thả bằng con trỏ.
- Giữ lại khóa học/PDF kiểm thử để người dùng mở xem. Không gọi AI xử lý PDF kiểm thử.
- Frontend Dockerfile chuyển sang `npm ci` với lockfile, thêm `.dockerignore` để tránh đưa `node_modules` Windows, `dist` và `.env` vào image. Image frontend đã build lại và container đã recreate với thay đổi này.

Giải thích bình dân: Docker nay chạy được thật; trình duyệt gửi file qua frontend, Laravel ghi vào MySQL và ổ lưu trữ của container. Việc sửa Dockerfile giúp máy các thành viên dùng cùng danh sách thư viện thay vì tự lấy phiên bản khác nhau.
