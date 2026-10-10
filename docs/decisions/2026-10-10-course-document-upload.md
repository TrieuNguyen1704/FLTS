# Quyết định triển khai phần Course / Document của Vương

Ngày: 10/10/2026. Cơ sở: main `f2dd333`, yêu cầu giao việc ba ảnh của Product Owner.

- Bổ sung unique `courses.code` bằng migration mới, không viết lại migration Sprint 1 đã áp dụng.
- Nếu có mã trùng, migration dừng và yêu cầu xử lý dữ liệu trước. Không tự đổi mã môn hoặc xóa khóa học.
- Giữ `stored_path/size_bytes` và API `/api` hiện tại để không phá frontend/queue/RAG Sprint 2/3. Đây là điểm lệch cần đồng bộ với ảnh/Project Bible trong lần chốt hợp đồng tiếp theo.
- Giới hạn upload mặc định 10 MB, lấy qua `config/documents.php`; nếu đổi giới hạn cần đồng bộ frontend/PHP/Nginx.
- Chỉ thêm chỉ mục phục vụ lọc khóa học theo chủ sở hữu, tài liệu theo khóa và thứ tự thời gian; phân trang list/dashboard còn là việc tiếp theo.
- Kiểm thử ban đầu native PHP/SQLite; sau khi người dùng yêu cầu mở Docker, đã chạy test trong API container và xác minh MySQL/browser. Không chạy pipeline AI/vector local cho task này.
- Frontend image dùng `npm ci` và `package-lock.json`; build context bỏ `node_modules`, `dist`, `.env` để tránh khác biệt Windows/Linux và đưa credential vào image.
- Mọi thay đổi nằm ở nhánh `feature/sprint1-vuong-courses-documents`, chưa merge main.
