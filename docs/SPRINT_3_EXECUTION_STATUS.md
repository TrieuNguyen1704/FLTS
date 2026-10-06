# Trạng thái triển khai Sprint 3 — Quiz Learning Object Vertical Slice

Ngày kiểm chứng: **06/10/2026**

Nhánh triển khai: `feature/sprint3-quiz-learning-objects`

Nhánh/commit cơ sở: `main` tại `f751e35` (PR #8)

Tài liệu này phân biệt **phần mềm đã chạy được ở local** với trạng thái quản trị Sprint. Chín PB core bên dưới đã có code, tích hợp, test tự động và một luồng trình duyệt end-to-end trên dữ liệu thật. Sprint chỉ nên được đóng về mặt quy trình sau khi PR được CI/review/merge và nhóm chấp nhận evidence tại Sprint Review.

## 1. Kết quả core commitment

| PB / US | Kết quả đã triển khai | Bằng chứng ngày 06/10 | Trạng thái kỹ thuật |
|---|---|---|---|
| PB24 / US-15 | FastAPI retrieve Top-K theo course/document, Gemini sinh Quiz, server map `citation_indexes` sang vector/source thật; Laravel lưu draft | Gọi Gemini thật tạo 3/3 câu có citation từ 5 matches; UI tạo Learning Object #1 từ Course 4 | Đã xác minh local |
| PB28 / US-19 | Pydantic giới hạn schema; kiểm tra đúng số câu, 4 lựa chọn khác nhau, correct index và citation index hợp lệ; output sai trả lỗi, không lưu | Pytest kiểm tra mapping, citation ngoài context và request sai | Đã xác minh local |
| PB29 / US-20 | UI/API nhận topic, difficulty, question count, document IDs, Top-K; Laravel validation và snapshot `generation_params` | PHPUnit kiểm tra params gửi qua boundary và version 1 | Đã xác minh local |
| PB30 / US-21 | Lecturer xem draft gồm câu hỏi, bốn lựa chọn, đáp án, giải thích và nguồn dẫn | Browser hiển thị 3 câu của Quiz thật cùng locator/vector source | Đã xác minh local |
| PB31 / US-22 | Lecturer sửa title, description, câu hỏi, lựa chọn, đáp án, giải thích trước publish | Browser đổi tiêu đề và lưu thành Version 2; PHPUnit kiểm tra persist | Đã xác minh local |
| PB33 / US-24 | Publish chỉ dành cho Lecturer sở hữu course; kiểm tra tối thiểu 3 câu, 4 lựa chọn, một đáp án, explanation và citation; Student không thấy draft | Browser publish Learning Object #1; Student chỉ thấy sau publish | Đã xác minh local |
| PB34 / US-25 | Trạng thái `draft/published/archived`; nội dung published bất biến; mỗi edit draft tạo snapshot; API trả `current_version` | UI hiển thị Version 1 và 2; PHPUnit kiểm tra published không sửa được | Đã xác minh local |
| PB35 / US-27 | Student workspace chỉ dùng course đã enrollment và learning object published | Browser Student mở Course 4 và thấy đúng một Quiz published | Đã xác minh local |
| PB36 / US-28 | Start/submit/score; không lộ đáp án trước submit; feedback đúng/sai, đáp án đúng, explanation và citation; lưu mọi retake, latest/best | Browser: lượt 1 = 33.33, lượt 2 = 100, tổng 2 lượt; PHPUnit kiểm tra option chéo bị từ chối | Đã xác minh local |

## 2. Stretch goals

- PB25 / US-16 Flashcards: **chưa triển khai**.
- PB32 / US-23 Regenerate: **chưa triển khai**.

Hai mục này là stretch goal theo quyết định Section 10 của `AntigravityTask.md`, không được trình bày như phần core đã hoàn thành.

## 3. Kiến trúc và dữ liệu mới

Các migration `2026_10_06_000014` đến `000020` tạo bảy bảng: `learning_objects`, `learning_object_versions`, `quizzes`, `quiz_questions`, `quiz_options`, `quiz_attempts`, `quiz_attempt_answers`. Migration chạy thành công ở batch 6 trên MySQL hiện hữu, không reset volume.

Luồng Lecturer:

`LearningObjectsView.vue` → `learningObjectService.generateQuiz()` → `POST /api/courses/{course}/learning-objects/quizzes` → `LearningObjectController@storeQuiz` → `RagService@generateQuiz` → `POST /internal/v1/generation/quiz` → Chroma retrieval → Gemini structured output → server citation mapping → transaction lưu draft/version/questions/options.

Luồng Student:

`StudentCourseView.vue` → chỉ lấy published objects → `QuizAttemptView.vue` → start attempt → submit toàn bộ answers → `QuizAttemptController@submit` kiểm tra option thuộc đúng question → chấm điểm trong transaction → trả feedback và cập nhật latest/best.

## 4. API chính

- `POST /internal/v1/generation/quiz` — internal FastAPI, bắt buộc bearer `AI_SERVICE_TOKEN`.
- `GET /api/courses/{course}/learning-objects` — owner Lecturer hoặc Student đã enrollment; Student chỉ nhận published.
- `POST /api/courses/{course}/learning-objects/quizzes` — tạo draft, Lecturer owner.
- `PATCH /api/courses/{course}/learning-objects/{learningObject}` — sửa draft và tạo version.
- `POST .../{learningObject}/publish` và `/archive` — lifecycle Lecturer owner.
- `POST .../{learningObject}/quiz-attempts` — Student bắt đầu/retake.
- `POST .../quiz-attempts/{attempt}/submit` — chấm điểm và trả feedback.
- `GET .../{learningObject}/quiz-attempts` — lịch sử, latest score và best score.

## 5. Kết quả kiểm tra thực tế

| Kiểm tra | Kết quả |
|---|---|
| `npm run build` | Pass, Vite build 60 modules |
| PHP syntax lint | Pass cho controller/model/migration/test mới |
| `docker compose exec -T ai pytest -q` | **19 passed**, 1 Starlette deprecation warning không chặn |
| `docker compose exec -T api php vendor/bin/phpunit --testdox` | **23 tests, 136 assertions**, pass |
| `docker compose ps` | 7 service running; API, AI, MySQL, Chroma, Mailpit healthy |
| Migration | 20 migration đều `Ran`; 7 migration Sprint 3 ở batch 6 |
| Chroma | **551 vectors**, giữ nguyên trước/sau rebuild |
| Gemini thật | `REAL_QUIZ_OK questions=3 grounded_questions=3 retrieval_matches=5 model=gemini-2.5-flash` |
| Web route | `/lecturer/courses/4/learning-objects` trả SPA HTTP 200 và hiển thị form thật |
| Browser E2E | Lecturer generate → preview → edit/version 2 → publish; Student workspace → submit → retake |

Sau browser E2E, dữ liệu demo được giữ lại để kiểm tra: 1 learning object, 2 versions, 3 questions, 12 options, 2 lượt hoàn thành và 6 attempt answers. Tài liệu/chunk/vector cũ vẫn tồn tại: MySQL có 551 chunks và 551 vector references; Chroma có 551 vectors.

## 6. Giới hạn và việc còn cần nhóm xử lý

- Chưa có PB25 Flashcards và PB32 Regenerate vì đây là stretch goals.
- `time_limit_minutes` được lưu và hiển thị nhưng chưa có bộ đếm cưỡng chế hết giờ; không nên demo như tính năng timed assessment hoàn chỉnh.
- Phiên bản hiện là immutable snapshot phục vụ audit; chưa có chức năng restore một snapshot cũ.
- Chưa có analytics cấp Lecturer, learning-event tracking tổng quát, anti-cheat hoặc randomization; các phần này thuộc backlog sau.
- Cần push nhánh, mở PR, chờ hai GitHub checks và review/merge. Không tự điền Actual hours hoặc nhận công thay thành viên trong workbook.
- API key/token vẫn chỉ nằm trong `.env` ignored. Không đưa chúng vào issue, PR, ảnh hoặc log evidence.

## 7. Kịch bản demo Sprint 3 ngắn

1. Đăng nhập Lecturer, mở Course 4 → **Tạo và quản lý Quiz**.
2. Chọn PDF đã processed, nhập topic, chọn difficulty/3 questions và tạo Quiz.
3. Chỉ ra draft, 4 lựa chọn/câu, đáp án, explanation và source locator; sửa tiêu đề rồi lưu để tạo version mới.
4. Publish và đăng xuất.
5. Đăng nhập Student, mở Course 4; xác nhận chỉ có Quiz published và chưa thấy đáp án.
6. Làm bài, nộp và xem score, đúng/sai, đáp án đúng, explanation/citation; bấm làm lại để chứng minh retake/latest/best.
