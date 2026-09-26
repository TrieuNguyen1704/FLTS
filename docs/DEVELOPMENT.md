# Quy ước phát triển Sprint 1

## Nhánh và review

- `main` là nhánh tích hợp có thể demo; không push trực tiếp khi repository đã có remote chung.
- Tạo nhánh ngắn theo dạng `feature/<pb>-<mô-tả>` hoặc `fix/<mô-tả>`; ví dụ `feature/pb12-document-upload`.
- Mỗi pull request ghi PB/US liên quan, cách chạy/test, thay đổi migration/API và giới hạn còn lại.
- PR và CI pass là điều kiện merge vào `main`. Review bởi một thành viên khác vẫn được khuyến nghị khi có người cùng quyền Write; ruleset hiện tại không bắt buộc approval để Scrum Master có thể merge sau khi các checks pass. Branch protection cần được nhóm kiểm tra lại trên GitHub khi thay đổi quyền/kế hoạch repository.

## Quy ước code

- Backend kiểm tra xác thực, role và ownership ở route/controller; UI không là cơ chế phân quyền.
- Endpoint API trả JSON; thay đổi schema dùng migration, không sửa database thủ công để tạo bằng chứng demo.
- Không log token/mật khẩu/tệp dạy học. `.env` không commit; chỉ cập nhật `.env.example`.
- Trạng thái xử lý phải phản ánh pipeline thật. Không đổi tài liệu sang `processed` nếu chưa có extraction/RAG worker thành công.
- Vue hiển thị rõ các tính năng prototype/chưa hỗ trợ; không dựng dữ liệu giả cho Quiz/RAG/analytics để coi là hoàn thành.

## Checklist pull request

- [ ] Có PB/US và acceptance criteria liên quan.
- [ ] Có test mới/cập nhật hoặc lý do vì sao chưa thể test.
- [ ] Có kiểm tra authorization và input validation cho endpoint mới.
- [ ] Migration/seed/backward compatibility được mô tả khi data model đổi.
- [ ] README/demo context được cập nhật nếu lệnh chạy hoặc scope thay đổi.
