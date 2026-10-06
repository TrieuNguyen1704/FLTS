# Trạng thái triển khai Sprint 3 — Quiz Learning Object Vertical Slice, Background Task Center & Student Enrollment

Ngày kiểm chứng: **06/10/2026**

Nhánh triển khai: `feature/sprint3-ux-enrollment-progress`

Nhánh/commit cơ sở: `feature/sprint3-quiz-learning-objects` tại `4892115`

Tài liệu này ghi nhận toàn bộ mã nguồn, cấu hình, kiểm thử tự động và bằng chứng end-to-end đã hoàn thành cho Sprint 3 theo các tiêu chuẩn kỹ thuật nghiêm ngặt.

---

## 1. Kết quả core commitment

| PB / US | Kết quả đã triển khai | Bằng chứng kỹ thuật | Trạng thái kỹ thuật |
|---|---|---|---|
| PB24 / US-15 | FastAPI retrieve Top-K theo course/document, Gemini sinh Quiz, server map `citation_indexes` sang vector/source thật; Laravel lưu draft | Gọi Gemini thật tạo 3/3 câu có citation từ 5 matches; UI tạo Learning Object từ Course 4 | Đã xác minh local |
| PB28 / US-19 | Pydantic giới hạn schema; kiểm tra đúng số câu, 4 lựa chọn khác nhau, correct index và citation index hợp lệ; output sai trả lỗi, không lưu | Pytest kiểm tra mapping, citation ngoài context và request sai | Đã xác minh local |
| PB29 / US-20 | UI/API nhận topic, difficulty, question count, document IDs, Top-K; Laravel validation và snapshot `generation_params` | PHPUnit kiểm tra params gửi qua boundary và version 1 | Đã xác minh local |
| PB30 / US-21 | Lecturer xem draft gồm câu hỏi, bốn lựa chọn, đáp án, giải thích và nguồn dẫn | Browser hiển thị các câu của Quiz thật cùng locator/vector source | Đã xác minh local |
| PB31 / US-22 | Lecturer sửa title, description, câu hỏi, lựa chọn, đáp án, giải thích trước publish | Browser đổi tiêu đề và lưu thành Version mới; PHPUnit kiểm tra persist | Đã xác minh local |
| PB33 / US-24 | Publish chỉ dành cho Lecturer sở hữu course; kiểm tra tối thiểu 3 câu, 4 lựa chọn, một đáp án, explanation và citation; Student không thấy draft | Browser publish Learning Object; Student chỉ thấy sau publish | Đã xác minh local |
| PB34 / US-25 | Trạng thái `draft/published/archived`; nội dung published bất biến; mỗi edit draft tạo snapshot; API trả `current_version` | UI hiển thị version snapshots; PHPUnit kiểm tra published không sửa được | Đã xác minh local |
| PB35 / US-27 | Student workspace chỉ dùng course đã enrollment và learning object published | Browser Student mở Course và thấy đúng Quiz published | Đã xác minh local |
| PB36 / US-28 | Start/submit/score; không lộ đáp án trước submit; feedback đúng/sai, đáp án đúng, explanation và citation; lưu mọi retake, latest/best | Chấm điểm độc lập; bảo lưu lịch sử làm bài; PHPUnit kiểm tra option chéo bị từ chối | Đã xác minh local |
| **PB UX / Task Center** | Background Task Center toàn cục trên topbar: theo dõi Document Processing và Quiz Generation; polling tập trung (3.5s active / 15s idle), tạm dừng khi tab ẩn và refresh tức thì khi tab hiện; đúng 1 toast thông báo khi xong/lỗi; thanh tiến trình indeterminate kèm tên stage thật; nút thử lại an toàn. | `BackgroundTaskController@index`, store `backgroundTasks.js`, component `BackgroundTaskCenter.vue` | Đã xác minh local & live test |
| **PB Enrollment** | Quản lý sinh viên khóa học: liệt kê sinh viên đã ghi danh (kèm số lượt làm quiz), tìm kiếm sinh viên khả dụng (chỉ sinh viên active chưa thuộc khóa), ghi danh có xác thực trạng thái, hủy ghi danh bảo lưu toàn vẹn lịch sử bài thi. | `CourseController` (`students`, `availableStudents`, `enroll`, `unenroll`), Tab "Sinh viên" trong `CourseDetailView.vue` | Đã xác minh local & live test |

---

## 2. Chi tiết Background Task Center (UX & Architecture)

- **Backend Aggregation (`GET /api/background-tasks`):**
  - Chỉ cho phép Giảng viên (`role = 'lecturer'`).
  - Tổng hợp các lượt xử lý tài liệu (`DocumentProcessingRun`) và tạo Quiz (`LearningObjectGenerationRun`) thuộc các khóa học mà Giảng viên sở hữu.
  - Trả về payload chuẩn hóa: `id`, `type`, `course_id`, `course_name`, `course_code`, `title`, `status`, `stage`, `stage_label`, `started_at`, `finished_at`, `error_message`, `retryable`, `target_url`, `is_active`.
  - Phân loại nhãn giai đoạn trực quan: "Đang trích xuất văn bản", "Đang làm sạch nội dung", "Đang phân đoạn nội dung", "Đang tạo vector ngữ nghĩa", "Đang lưu trữ chỉ mục vector", "Đang phân tích tài liệu và sinh câu hỏi", "Đã hoàn tất", "Xử lý thất bại".
  - **Không dùng phần trăm giả định (no fake percentage):** Dùng hiệu ứng indeterminate bar đồng bộ nhãn giai đoạn backend thực tế.

- **Store & Polling Strategy (`frontend/src/stores/backgroundTasks.js`):**
  - Chu kỳ polling: **3.5 giây** khi có tác vụ đang hoạt động (`active_count > 0`), **15 giây** khi nhàn rỗi (`active_count === 0`).
  - Lắng nghe sự kiện `visibilitychange`: Tự động ngắt timer khi tab trình duyệt bị ẩn (`document.visibilityState === 'hidden'`) để tiết kiệm tài nguyên mạng/CPU; lập tức gọi `fetchTasks()` và kích hoạt lại timer ngay khi người dùng chuyển lại tab (`visible`).
  - Cơ chế thông báo chính xác 1 lần: Sử dụng `Map(taskId => status)` ghi nhận trạng thái đã toast. Chỉ hiển thị đúng 1 thông báo toast khi tác vụ chuyển từ đang chạy sang hoàn thành (`success`) hoặc thất bại (`error`). Lần tải trang đầu tiên không spam thông báo cho các tác vụ cũ.

- **Giao diện Topbar & Drawer (`BackgroundTaskCenter.vue`):**
  - Tích hợp tại `AppTopbar.vue`: Nút "Tác vụ" kèm icon và huy hiệu số lượng tác vụ đang xử lý (có hiệu ứng xoay nhẹ và màu xanh nhấn khi bận).
  - Ngăn kéo bên phải (Slide-over drawer) cho phép xem chi tiết từng tác vụ, thời gian tạo, thông điệp lỗi an toàn (không lộ URL nội bộ/stack trace), nút "Thử lại" trực tiếp và liên kết điều hướng nhanh đến khóa học tương ứng.

---

## 3. Chi tiết Quản lý sinh viên (Student Enrollment Management)

- **Phân quyền và bảo mật nghiêm ngặt (RBAC):**
  - Chỉ Giảng viên sở hữu khóa học mới có quyền xem danh sách, tìm kiếm sinh viên khả dụng, ghi danh hoặc hủy ghi danh. Giảng viên khác hoặc sinh viên đều nhận mã HTTP `403 Forbidden`.
  - Ghi danh chỉ chấp nhận tài khoản có `role = 'student'` và `account_status = 'active'`. Tài khoản bị đình chỉ (`suspended`) bị chặn với mã `422`.
- **Bảo toàn lịch sử bài kiểm tra:**
  - Hủy ghi danh chỉ xóa liên kết trong bảng pivot `course_enrollments`.
  - Toàn bộ bản ghi trong bảng `quiz_attempts` và `quiz_attempt_answers` được giữ nguyên vẹn 100%, phục vụ cho mục đích thống kê, lưu trữ và khiếu nại điểm số.
- **Giao diện Tab "Sinh viên" trong `CourseDetailView.vue`:**
  - Bảng danh sách sinh viên: Họ và tên, Email, Trạng thái (Active), Thời gian ghi danh, Số lượt làm Quiz trong khóa học.
  - Ô tìm kiếm / lọc nhanh sinh viên theo tên hoặc email.
  - Modal "Ghi danh sinh viên mới": Ô tìm kiếm thời gian thực sinh viên khả dụng chưa thuộc khóa học, nút bấm ghi danh một chạm.
  - Modal "Xác nhận hủy ghi danh": Cảnh báo rõ ràng kèm ghi chú bảo lưu lịch sử làm bài thi.

---

## 4. Tái cấu trúc giao diện Course Detail & Toàn hệ thống

- **CourseDetailView 4 Tabs chuyên nghiệp:**
  - Tab 1: **Tổng quan (Overview):** Thông tin mã môn, tên môn, giảng viên, thẻ thống kê số tài liệu / số quiz / số sinh viên, liên kết nhanh.
  - Tab 2: **Tài liệu (Documents):** Kéo thả tải tệp, danh sách tệp, dung lượng, thời gian, trạng thái vector RAG, tải về, xử lý/thử lại, xóa.
  - Tab 3: **Quiz trắc nghiệm (Quizzes):** Danh sách các bài Quiz của khóa học, thông số số câu hỏi, điểm đạt, trạng thái Draft/Published/Archived, nút tạo Quiz mới và nút mở trình chỉnh sửa Quiz.
  - Tab 4: **Sinh viên (Students):** Bảng danh sách và modal ghi danh/hủy ghi danh.
  - Đồng bộ query URL `?tab=overview|documents|quizzes|students`, hỗ trợ điều hướng trực tiếp từ các thông báo hoặc liên kết bên ngoài.
- **Làm sạch ngôn ngữ thiết kế:**
  - Loại bỏ hoàn toàn các slogan tiếp thị ("Powered by AI", "RAG platform").
  - Loại bỏ các icon/emoji trang trí thừa thãi.
  - Thống nhất bảng màu chuẩn mực giáo dục (`#17275a`, `#2958d8`, `#64748b`, `#e2e8f5`, `#f8fafc`).
  - Đảm bảo hiển thị hoàn hảo trên Desktop, Laptop (1366x768) và Mobile.

---

## 5. Kết quả kiểm tra tự động và dữ liệu

| Hạng mục kiểm tra | Lệnh thực hiện | Kết quả thực tế |
|---|---|---|
| **PHPUnit Test Suite** | `docker compose exec -T api php vendor/bin/phpunit --testdox` | **33 tests, 195 assertions PASSED (100%)** |
| **FastAPI Pytest** | `docker compose exec -T ai pytest -q` | **20 passed (100%)** |
| **Frontend Production Build** | `docker compose build web` | **Vite build thành công (67 modules, 0 error)** |
| **ChromaDB Vector Count** | Query collection `flts_document_chunks` | **551 vectors nguyên vẹn** |
| **Docker Compose Services** | `docker compose ps` | **8 containers Up & Healthy** |
| **Live API: Background Tasks** | `GET /api/background-tasks` | Trả về 16 tác vụ thực tế (Document & Quiz runs) |
| **Live API: Students List** | `GET /api/courses/4/students` | Liệt kê sinh viên đã ghi danh + attempts count |
| **Live API: Available Students** | `GET /api/courses/4/students/available` | Trả về sinh viên active chưa vào lớp |
| **Live API: Enroll & Unenroll** | `POST` & `DELETE` enrollments | Ghi danh và hủy ghi danh thành công, mã 200 |

---

## 6. Điều tra sự cố Document 8 / Run 19

- **Tệp tin:** `PHI_150_BG_ThanhTD_041121.doc (1).pdf` (kích thước 4,126,367 bytes ~ 4.12 MB, hàng trăm trang giáo trình).
- **Trạng thái thực tế trong CSDL:** `status: failed`, `stage: failed`, `started_at: 2026-10-06 22:10:18`, `finished_at: 2026-10-06 22:20:18`.
- **Nguyên nhân cốt lõi:** Quá trình trích xuất và embedding văn bản lớn gặp giới hạn rate limit 100 RPM của gói Gemini Free Tier. Do phải chờ phân bổ quota qua từng batch, thời gian xử lý chạm ngưỡng timeout 600 giây của Nginx/cURL (`Operation timed out after 600001 milliseconds with 0 bytes received for http://ai:8001/internal/v1/documents/process`).
- **Kết luận:** Tác vụ **không bị treo vô tận**. Job Laravel đã bắt đúng ngoại lệ cURL timeout sau 600s, chạy vào `ProcessTeachingDocument::failed()`, cập nhật trạng thái `failed`, lưu `retryable: true` để Giảng viên có thể bấm "Thử xử lý lại" khi quota khả dụng.
