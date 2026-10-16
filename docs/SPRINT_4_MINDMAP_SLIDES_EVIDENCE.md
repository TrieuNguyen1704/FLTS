# Bằng chứng thực thi Sprint 4 — Mindmap & Interactive Slides

Ngày kiểm chứng: **16/10/2026**
Nhánh: `feature/sprint4-mindmap-and-slides`
Baseline: `main` tại merge commit `e594a9d`

## Phạm vi đã chạy

| Hạng mục | Kết quả | Bằng chứng |
|---|---|---|
| PB26 / US-17 Mindmap | Đã chạy vertical slice | FastAPI retrieval + structured generation; Laravel queue/version/publish; SVG viewer/editor; Student published view |
| PB27 / US-18 Slides | Đã chạy vertical slice | FastAPI structured generation; Laravel queue/version/publish; slide editor/player; Student published view |
| Citation grounding | Đã kiểm thử | Model chỉ trả index; FastAPI map metadata nguồn; edit API giữ citation server-side |
| RBAC | Đã kiểm thử | Owner-only create/edit/publish; Student enrolled và chỉ thấy published; Student response không có speaker notes |
| Background tasks | Đã kiểm thử | `mindmap_generation`/`slide_generation`, trạng thái queue và editor target URL |

## E2E với provider thật

- Course: ID 4, `Flipped Learning Foundations`.
- Processed source: Document ID 5.
- Mindmap: Learning Object ID 11; attempt 4 `completed`; version 1; 4 nodes; đã publish và mở được tại `/student/courses/4/mindmaps/11`.
- Slides: Learning Object ID 12; attempt 2 `completed`; version 1; 5 slides; đã publish và mở được tại `/student/courses/4/slides/12`.
- Trình duyệt Lecturer đã xác minh Mindmap zoom controls/cây/citation UI và Slide 1 → Slide 2, speaker notes.
- Trình duyệt Student đã xác minh Course 4 hiển thị 1 Mindmap và 1 Slide; Slide có 5 trang và không hiển thị speaker notes.

Hai lỗi thật đã gặp và được xử lý:

1. Google SDK client từng bị đóng sớm do gọi qua temporary expression; sửa bằng tham chiếu client sống suốt request.
2. Gemini Mindmap recursive schema từng trả `null` trong mảng children; FastAPI loại null placeholder trước Pydantic validation. Một attempt khác nhận Gemini `503 UNAVAILABLE/high demand`; queue lưu lỗi an toàn và retry thành công.

## Kết quả kiểm thử thực tế

```text
docker compose exec -T api php vendor/bin/phpunit --testdox
OK (47 tests, 314 assertions)

docker compose exec -T ai pytest -q
33 passed, 1 non-blocking Starlette deprecation warning

docker compose build api ai web
Vite: 98 modules transformed; production build succeeded

docker compose exec -T ai python -c "import main; print(main.chroma_collection().count())"
645
```

`docker compose ps` xác nhận đủ 8 service chạy: `api`, `ai`, `chroma`, `mysql`, `mailpit`, `web`, `queue-worker`, `generation-worker`; các service có healthcheck đều healthy.

## Giới hạn và phần chưa tự tuyên bố Done

- Gemini Free Tier/độ tải provider có thể trả 429/503; sản phẩm dùng queue, safe error và retry, chưa có SLA production.
- Mindmap hiện sửa nhãn nhưng không thêm/xóa/di chuyển topology; đây là lựa chọn bảo toàn grounding trong vertical slice.
- Slides chưa có hình ảnh/media, export PPTX/PDF, collaborative editing hoặc analytics.
- Mindmap chưa có export ảnh/PDF, layout algorithm nâng cao hoặc lưu trạng thái fold của từng Student.
- Chưa hoàn tất báo cáo luận văn/PR review/CI evidence cho branch này; các bước đó là governance riêng với implementation evidence.
