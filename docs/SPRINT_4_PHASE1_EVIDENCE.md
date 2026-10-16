# Bằng chứng Sprint 4 Phase 1 — Flashcard Vertical Slice

> Ngày chạy: 16/10/2026
> Nhánh: `feature/sprint4-flashcard-vertical-slice`
> Phạm vi: PB25 / US-16 carry-over, không bao gồm Mindmap, Slides hay analytics.

## 1. Luồng đã chạy thực tế

1. Đăng nhập Lecturer bằng tài khoản seed.
2. Mở course `MTH-254 — Toán Rời Rạc`, chọn tab Flashcard.
3. Chọn tài liệu PDF đã processed, topic `Quan hệ, ánh xạ và hàm số`, 5 thẻ, `top_k=10`, `max_pages=3`.
4. API trả ngay object draft ở trạng thái queued; Task Center hiển thị một background task và người dùng có thể chuyển trang.
5. `generation-worker` log job `GenerateFlashcardLearningObject` hoàn tất trong khoảng 20 giây.
6. Editor hiển thị đủ 5 thẻ, mỗi thẻ có document name và source locator thật. Công thức như `$f(0)=3$` và `$f: B^n \\rightarrow B$` được `MathText.vue` render bằng KaTeX.
7. Learning Object ID 9 được publish. Tài khoản Student đã enroll course 5 gọi API thành công và workspace hiển thị đúng 1 bộ/5 thẻ.
8. Student mở player, lật thẻ, chọn “Đã hiểu”; UI chuyển từ thẻ 1 sang thẻ 2 và cập nhật “Đã đánh dấu 1 thẻ”.

Test E2E này tạo và giữ một bộ Flashcard published trong database demo để nhóm tiếp tục trình bày. Không có vector nào được tạo/xóa bởi generation Flashcard.

## 2. Kết quả tự động

| Kiểm tra | Kết quả thực tế |
|---|---|
| Laravel PHPUnit | 41/41 pass, 266 assertions |
| FastAPI Pytest | 26/26 pass; 1 Starlette deprecation warning |
| Vite production build | Pass, 86 modules transformed |
| Docker Compose | 8/8 services Up; health checks đạt ở api/ai/mysql/chroma/mailpit |
| MySQL migration | `2026_10_16_000023_expand_learning_object_types` Ran, batch 9 |
| Chroma collection count trước generation | 645 |

## 3. Coverage kiểm thử mới

- Laravel: idempotent request UUID; owner/RBAC; processed-document scope; job persistence; safe provider error + retry; version edit; publish; Student sees published but not draft.
- FastAPI: bearer token inherited from internal router; strict request bounds; authoritative citation mapping; max-page limiting; invalid citation index and duplicate front rejection.
- Browser: Lecturer generate/background transition/editor; Student published listing/player/flip/navigation/local review marker.

## 4. Lệnh tái kiểm tra an toàn

Các lệnh dưới đây không reset database hay volume:

```powershell
# Rebuild và khởi động toàn bộ service, giữ nguyên named volumes hiện có.
docker compose up --build -d

# Kiểm tra trạng thái 8 service.
docker compose ps

# Chạy regression Laravel bằng database test SQLite cô lập.
docker compose exec -T api php vendor/bin/phpunit --testdox

# Chạy regression FastAPI.
docker compose exec -T ai pytest -q

# Kiểm tra số vector hiện tại mà không sửa collection.
docker compose exec -T ai python -c "import main; print(main.chroma_collection().count())"
```

Không dùng `docker compose down -v` hoặc `migrate:fresh` vì hai lệnh này phá dữ liệu local.

## 5. Chưa hoàn thành

- Mindmap generation/viewer/editor.
- Interactive Slides generation/player/editor.
- Persist “Đã hiểu / Cần ôn lại”, spaced repetition scheduling và analytics.
- Restore một version cũ; hiện version là snapshot audit, không phải restore workflow.
- Production multi-session auth; login mới của cùng account thay thế token cũ.

## 6. Bảo mật cần xử lý ngoài code

Một Gemini key thật đã được phát hiện trong `docs/SPRINT_2_EXECUTION_PLAN.md` từ lịch sử trước Phase 1 và được thay bằng placeholder. Nhóm cần rotate key tại Google AI Studio vì secret có thể vẫn tồn tại trong commit history; key mới chỉ đặt trong `.env` bị Git ignore.
