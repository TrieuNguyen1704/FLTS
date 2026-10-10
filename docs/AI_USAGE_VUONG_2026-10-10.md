# AI usage log — phần Course / Document của Vương

Ngày: 10/10/2026. Tool: Codex. Model cụ thể trong giao diện: không xác nhận.

Yêu cầu của người dùng: thực hiện và rà soát phần giao việc trong ba ảnh: quản lý khóa học, tài liệu upload, FileDropzone, test và Git.

AI đã đọc main, đối chiếu ảnh, đề xuất và viết Form Requests, migration unique/index, xử lý lỗi lưu file, bước xác nhận upload và `CourseAndDocumentTest.php`. AI tạo bản đối chiếu, Decision Log, chạy PHPUnit và frontend build.

Kết quả tự động: 46 tests / 283 assertions passed, Vue build passed. Chưa có xác minh browser/MySQL/Docker cho commit này. Review bởi thành viên nhóm: **pending**; không coi tự kiểm tra của AI là review của con người.

Người phụ trách task: Đặng Trung Vương. Cần đọc code, chạy lại các luồng và giải thích được trước khi merge. Không gửi tệp giảng dạy, dữ liệu cá nhân sinh viên hoặc credential cho API AI trong task này. Các test dùng dữ liệu giả và storage fake.
