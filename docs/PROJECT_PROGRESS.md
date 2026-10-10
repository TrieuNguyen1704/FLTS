# Báo cáo Tiến độ Toàn diện Dự án FLTS (Flipped Learning Teaching System)

> **Cập nhật lần cuối:** 10/10/2026 — 21:45 (GMT+7)  
> **Nhánh phát triển:** `feature/fix-071026`  
> **Phiên bản hệ thống:** v1.2.0-coursera-ui  
> **Trạng thái tổng thể:** Hoàn thành toàn bộ tính năng cốt lõi (Core MVP), tối ưu hóa RAG Demo, sửa lỗi hạ tầng Docker 502 và nâng cấp toàn diện giao diện theo chuẩn **Coursera Design System (CDS)**.

---

## 1. Tổng quan Dự án & Mục tiêu Nghiên cứu

Hệ thống **FLTS (Flipped Learning Teaching System)** là đồ án Capstone xây dựng nền tảng hỗ trợ mô hình **Lớp học đảo ngược (Flipped Classroom)** ứng dụng công nghệ **AI tạo sinh (Generative AI)** và **RAG (Retrieval-Augmented Generation)**.

### Mục tiêu cốt lõi:
1. **Tự động hóa trích xuất tri thức từ giáo trình giảng dạy:** Giảng viên tải lên giáo trình (PDF/DOC/DOCX), hệ thống tự động bóc tách, làm sạch, phân đoạn (semantic chunking) và lưu trữ vector vào ChromaDB.
2. **Biên soạn Quiz trắc nghiệm tự động theo chuẩn Bloom:** AI tự động truy xuất các đoạn tài liệu liên quan nhất (RAG Top-K) để sinh câu hỏi trắc nghiệm khách quan có 4 lựa chọn, đáp án đúng, giải thích chi tiết và trích dẫn chính xác vị trí tài liệu nguồn.
3. **Hiển thị chuẩn xác công thức toán học & khoa học:** Tích hợp KaTeX render công thức toán học dạng LaTeX inline `$..$` và block `$$..$$`, tự động phát hiện và sửa các công thức bị lỗi ký tự từ giáo trình gốc.
4. **Theo dõi tiến độ học tập và tự đánh giá của Sinh viên:** Sinh viên ghi danh vào khóa học bằng mã một chạm (`Course Join Code`), làm bài kiểm tra nhiều lần để củng cố kiến thức trước giờ lên lớp. Hệ thống ghi nhận điểm mới nhất, điểm cao nhất và toàn bộ lịch sử thi.
5. **Giao diện người dùng chuyên nghiệp chuẩn EdTech:** Hệ thống UI/UX được thiết kế theo **Coursera Design System**, mang lại trải nghiệm học tập hiện đại, tối giản và thân thiện.

---

## 2. Kiến trúc Hệ thống & Ngăn xếp Công nghệ

| Thành phần | Công nghệ / Thư viện | Vai trò |
|---|---|---|
| **Frontend Client** | Vue 3 (Composition API), Vite 5, Vue Router 4, KaTeX, CSS Custom Properties | Giao diện Single Page Application (SPA) chuẩn Coursera |
| **Web Server / Reverse Proxy** | Nginx Alpine (Dynamic DNS resolver, Upstream load balance) | Định tuyến API (`/api`), phục vụ frontend tĩnh (`:8080`) |
| **Backend API Gateway** | Laravel 11, PHP 8.4, Laravel Sanctum, Eloquent ORM | Quản lý nghiệp vụ, phân quyền RBAC, xác thực JWT, Queue jobs |
| **AI / RAG Microservice** | FastAPI, Python 3.12, Google GenAI SDK (Gemini 2.5/Flash), PyMuPDF | Trích xuất tài liệu, semantic chunking, sinh vector, prompt AI |
| **Vector Database** | ChromaDB 0.5.23 (Persistent HTTP Server) | Lưu trữ và tìm kiếm vector embedding đoạn văn bản giáo trình |
| **Relational Database** | MySQL 8.4 LTS | Lưu trữ thông tin người dùng, khóa học, quiz, lượt làm bài |
| **Queue Workers** | 2 Container PHP CLI (`queue-worker`, `generation-worker`) | Xử lý bất đồng bộ các tác vụ nặng (xử lý tài liệu & sinh quiz) |
| **Email Testing** | Mailpit v1.22 | Giả lập gửi email xác thực, khôi phục mật khẩu (`:8025`) |

---

## 3. Lịch sử & Tiến độ Triển khai theo Từng Giai đoạn

### 🔹 Sprint 1: Nền tảng cốt lõi, Xác thực & Quản lý Khóa học
- **Hoàn thành:**
  - Thiết lập hạ tầng Docker Compose hoàn chỉnh gồm 8 dịch vụ cô lập.
  - Hệ thống xác thực bằng Token (Sanctum), mã hóa mật khẩu bcrypt, khôi phục mật khẩu qua email token.
  - Phân quyền nghiêm ngặt 3 vai trò (RBAC): `Admin`, `Lecturer`, `Student`.
  - Quản trị viên quản lý danh sách tài khoản, khóa/mở khóa tài khoản, chỉ định vai trò.
  - Giảng viên tạo, sửa, xóa khóa học, quản lý danh sách khóa học phụ trách.
  - Thiết kế bảng điều khiển tổng quan cho từng vai trò.

### 🔹 Sprint 2: RAG Pipeline & Trích xuất Giáo trình
- **Hoàn thành:**
  - Xây dựng Microservice AI với FastAPI: tích hợp PyMuPDF trích xuất text từ PDF, xử lý file Word.
  - Bộ lọc làm sạch văn bản thông minh (`clean_text`): Chuẩn hóa khoảng trắng, sửa lỗi giải mã font Symbol cổ điển (biến ký tự Hy Lạp / tập hợp bị vỡ hạt như `È` thành `∪`, `Ç` thành `∩`, `Î` thành `∈`).
  - Thuật toán phân đoạn văn bản thông minh (Semantic Chunking) bảo đảm độ dài đoạn và ngữ cảnh liên kết.
  - Tích hợp ChromaDB lưu trữ vector và metadata (document name, page number, chunk index).
  - Tích hợp RAG Workbench: Tìm kiếm ngữ nghĩa đoạn văn bản tương đồng theo truy vấn của Giảng viên.
  - Nạp thành công giáo trình và tạo **551 vector embedding** nguyên vẹn trong CSDL ChromaDB.

### 🔹 Sprint 3: AI Quiz Generation, Student Enrollment & Background Task Center
- **Hoàn thành:**
  - **Tạo Quiz tự động bằng AI RAG:** Giảng viên chỉ cần chọn chủ đề, số câu hỏi, độ khó, tài liệu nguồn; AI tự động trích xuất các chunk sát nhất và sinh câu hỏi trắc nghiệm đạt chuẩn Pydantic schema validation.
  - **Cơ chế Snapshot & Versioning Quiz:** Lưu trữ phiên bản độc lập (Version 1, Version 2,...). Quiz ở trạng thái `draft` cho phép sửa nội dung; khi `published` sẽ trở thành bất biến nhằm bảo đảm tính công bằng khi sinh viên làm bài.
  - **Giao diện làm bài cho Sinh viên:** Làm bài kiểm tra, nộp bài, tính điểm tự động, xem giải thích chi tiết và vị trí đoạn văn trích dẫn. Lưu trữ toàn bộ lịch sử điểm số (`latest_score`, `best_score`).
  - **Mã ghi danh một chạm (Course Join Code):** Mỗi khóa học sở hữu một mã `FLTS-XXXXXX`. Giảng viên có thể sao chép, làm mới mã hoặc tạm đóng ghi danh. Sinh viên chỉ cần nhập mã là tham gia ngay vào khóa học.
  - **Quản lý danh sách sinh viên:** Giảng viên theo dõi sinh viên trong lớp, số lượt làm quiz của từng em, hỗ trợ ghi danh chủ động hoặc hủy ghi danh (vẫn bảo lưu 100% lịch sử bài thi).
  - **Trung tâm tác vụ nền toàn cục (Background Task Center):** Theo dõi tiến độ xử lý tài liệu và sinh Quiz theo thời gian thực (real-time polling), hỗ trợ tính năng thử lại khi có lỗi mạng.
  - **Tích hợp KaTeX render công thức toán học:** Component `MathText.vue` hiển thị chính xác mọi biểu thức toán học dạng LaTeX.

### 🔹 Giai đoạn Hoàn thiện & Tinh chỉnh Nâng cao (07/10/2026 - 10/10/2026)
- **1. Sửa lỗi khởi động Docker & 502 Bad Gateway:**
  - **Vấn đề:** Khi khởi động đồng thời các container, container `api` khởi chạy trước khi MySQL hoàn tất cấu hình ban đầu, dẫn đến crash container; Nginx cache sai IP cũ của container `api` dẫn đến lỗi `502 Bad Gateway`.
  - **Giải pháp:** Thêm vòng lặp kiểm tra PDO readiness trong `backend/Dockerfile` để API chỉ phục vụ khi MySQL sẵn sàng; cấu hình `resolver 127.0.0.11 valid=10s` trong `frontend/nginx.conf` để phân giải IP động cho upstream.
- **2. Tối ưu hóa Prompt AI tự động sửa lỗi công thức:**
  - Bổ sung quy tắc kiểm tra và sửa lỗi công thức toán học trong prompt hệ thống tại `ai-service/main.py`. AI tự động phát hiện các công thức bị lỗi do OCR/giáo trình gốc và chuyển thành cú pháp LaTeX chuẩn (`$..$` và `$$..$$`).
- **3. Bổ sung tính năng Xóa Quiz (Delete Learning Object):**
  - Thêm endpoint `DELETE /api/courses/{course}/learning-objects/{learningObject}` với xác thực quyền sở hữu của Giảng viên.
  - Xóa theo tầng (Cascade deletion): Xóa sạch câu hỏi, đáp án, lượt làm bài của sinh viên, lịch sử phiên bản và bản ghi tác vụ sinh quiz.
  - Bổ sung modal cảnh báo an toàn trên UI (`CourseDetailView`, `LearningObjectsView`, `QuizEditorView`).
  - Bổ sung 2 bài test tự động trong `SprintThreeQuizApiTest.php`, nâng tổng số test PHPUnit lên **36/36 tests PASSED (100%)**.
- **4. Tối ưu hóa Chế độ Demo RAG (Fast Demo Ingestion):**
  - **Vấn đề:** Gói Gemini API Free Tier có giới hạn 100 RPM (Requests Per Minute). Khi Giảng viên tải file PDF lớn hàng trăm trang, thời gian chờ nạp vượt quá 600 giây cURL timeout.
  - **Giải pháp:** Bổ sung tham số `max_pages` vào `ai-service/rag_pipeline.py`. Giảng viên có thể chọn giữa:
    - ⚡ *Xử lý nhanh cho Demo (25 trang đầu):* Xong nhanh trong 1–2 phút để trình diễn bảo vệ đồ án mượt mà.
    - 📖 *Xử lý toàn bộ tài liệu:* Nạp toàn diện toàn bộ giáo trình.
  - Bổ sung unit test trong `test_rag_pipeline.py`, nâng tổng số test Pytest lên **22/22 tests PASSED (100%)**.
- **5. Nâng cấp toàn diện giao diện theo Coursera Design System (CDS):**
  - Thay đổi toàn bộ phong cách đồ họa, bảng màu, typography sang chuẩn Coursera:
    - **Palette:** Royal Blue `#0056D2`, Midnight Navy `#002D72`, Tint `#EBF3FF`, Canvas `#F5F7FA`.
    - **Header & Sidebar:** Topbar với Avatar viết tắt tên người dùng (Initials), Sidebar nền trắng phẳng với icon SVG cho từng mục điều hướng.
    - **Course Card:** Thẻ khóa học có dải banner gradient trên cùng, huy hiệu mã môn học, tên giảng viên và nút truy cập.
    - **Course Detail:** Khối Dark Navy Hero Banner trang trọng, thanh tab gạch dưới (Flat Underline Tabs), thẻ mã ghi danh nét đứt sang trọng.
    - **Quiz Assessment:** Giao diện làm bài kiểm tra chuẩn Coursera với thẻ câu hỏi, các ô lựa chọn đáp án dạng radio card viền xanh khi chọn, hộp giải thích trích dẫn tài liệu nguồn rõ ràng.
    - **Authentication:** Màn hình đăng nhập chia đôi ấn tượng kèm các nút bấm điền nhanh tài khoản Demo (Giảng viên, Sinh viên, Admin) hỗ trợ Hội đồng chấm điểm kiểm thử tức thì.

---

## 4. Bảng Tổng hợp Độ bao phủ Kiểm thử Tự động (Testing Coverage)

| Bộ kiểm thử | Công cụ | Số lượng bài test | Kết quả thực tế | Tỷ lệ thành công |
|---|---|---|---|---|
| **Backend Feature & Unit Tests** | PHPUnit 11.5 (PHP 8.4) | 36 bài test (228 assertions) | **36 PASSED** | **100%** |
| **AI Microservice & RAG Tests** | Pytest 8.3 (Python 3.12) | 22 bài test | **22 PASSED** | **100%** |
| **Frontend Production Build** | Vite 5.4 | 80 modules transformed | **Build PASSED (3.46s)** | **100%** |
| **ChromaDB Vector Integrity** | Collection `flts_document_chunks` | 551 chunks | **551 Vectors nguyên vẹn** | **100%** |
| **Container Healthcheck** | Docker Compose | 8 containers | **8/8 Up & Healthy** | **100%** |

---

## 5. Dữ liệu Kiểm thử & Tài khoản Mẫu (Demo Credentials)

| Vai trò | Email đăng nhập | Mật khẩu | Quyền hạn chính |
|---|---|---|---|
| **Giảng viên (Lecturer)** | `lecturer@flts.test` | `password` | Tạo khóa học, nạp PDF, sinh Quiz bằng AI, xem Task Center, quản lý sinh viên |
| **Sinh viên (Student)** | `student@flts.test` | `password` | Nhập mã tham gia khóa học, làm Quiz trắc nghiệm, xem giải thích và điểm số |
| **Quản trị viên (Admin)** | `admin@flts.test` | `password` | Quản lý toàn bộ danh sách tài khoản, khóa/mở khóa tài khoản, phân quyền vai trò |

---

## 6. Danh mục Tệp nguồn Quan trọng trong Dự án

```
FLTS/
├── ai-service/                     # Microservice AI & RAG (FastAPI + ChromaDB)
│   ├── main.py                     # API endpoints: /process, /query, /generate-quiz
│   ├── rag_pipeline.py             # Trích xuất PDF, clean text, chunking, max_pages
│   └── tests/                      # 22 bài test Pytest tự động
├── backend/                        # Backend API Gateway (Laravel 11)
│   ├── app/Http/Controllers/       # Controllers: Course, Document, LearningObject, Task...
│   ├── app/Jobs/                   # Asynchronous Queue Jobs xử lý tài liệu & sinh Quiz
│   ├── app/Services/               # RagService kết nối HTTP tới ai-service
│   ├── routes/api.php              # Toàn bộ định tuyến API có bảo vệ Sanctum
│   └── tests/Feature/              # 36 bài test PHPUnit tự động
├── frontend/                       # Single Page Application (Vue 3 + Vite)
│   ├── src/assets/main.css         # Hệ thống Design Tokens Coursera (CDS)
│   ├── src/components/             # AppTopbar, AppSidebar, CourseCard, MathText, Dropzone...
│   ├── src/views/                  # 14 views chính cho Lecturer, Student, Admin, Auth
│   └── nginx.conf                  # Nginx proxy định tuyến với dynamic DNS resolver
├── docs/                           # Tài liệu kỹ thuật và báo cáo tiến độ
│   ├── PROJECT_PROGRESS.md         # File này: Tổng hợp toàn bộ tiến độ dự án
│   ├── CODEBASE_GUIDE_VI.md        # Hướng dẫn chi tiết kiến trúc mã nguồn tiếng Việt
│   └── SPRINT_3_EXECUTION_STATUS.md# Báo cáo chi tiết Sprint 3
└── docker-compose.yml              # Khởi chạy 8 containers toàn hệ thống
```

---

## 7. Đánh giá & Định hướng cho Buổi Báo cáo Đồ án

1. **Tính hoàn thiện của Đề tài:** Dự án đã hoàn thiện 100% các cam kết từ Sprint 1 đến Sprint 3, đáp ứng đầy đủ yêu cầu của một hệ thống EdTech hiện đại hỗ trợ mô hình Lớp học đảo ngược.
2. **Tính thẩm mỹ và Chuyên nghiệp:** Giao diện Coursera mang lại vẻ ngoài trang nhã, tin cậy, đạt tiêu chuẩn của các nền tảng học trực tuyến hàng đầu thế giới.
3. **Độ ổn định cao khi Demo:** Nhờ tính năng **Xử lý nhanh cho Demo (25 trang)** và các nút **Điền nhanh tài khoản Demo**, buổi trình diễn trước Hội đồng phản biện sẽ diễn ra mượt mà, không gặp rủi ro nghẽn mạng hay lỗi chờ quota.
