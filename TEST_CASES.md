# SoulSync - Test Cases

This document contains a comprehensive list of test cases for the SoulSync application, covering all primary features including Setup, Registration, Login, and the Matching Dashboard.

## 1. Database Setup & Initialization (`api/setup.php` & `api/test.php`)

| Test ID | Feature | Test Scenario | Steps to Execute | Expected Result |
|---------|---------|---------------|------------------|-----------------|
| `TC_SU_01` | DB Setup | Successfully initialize the database | 1. Ensure MySQL is running.<br>2. Navigate to `api/setup.php` in the browser. | A success JSON response: `{"status": "success", "message": "Banco de dados inicializado!..."}`. Tables are created. |
| `TC_SU_02` | DB Setup | Re-run initialization | 1. Navigate to `api/setup.php` after it has already run. | Script runs successfully again without throwing fatal errors (handles `CREATE TABLE IF NOT EXISTS`). |
| `TC_SU_03` | DB test | Verify DB Connection | 1. Navigate to `api/test.php`. | JSON output with `"mysql_connection": "OK"`, `"database": "EXISTE"`, and `"table_count": 4`. |

---

## 2. User Registration (`src/register.html` & `api/register.php`)

### Frontend Validation

| Test ID | Feature | Test Scenario | Steps to Execute | Expected Result |
|---------|---------|---------------|------------------|-----------------|
| `TC_RG_01` | UI Validation | Step 1: Missing Required Fields | 1. Open `register.html`.<br>2. Leave "Full Name" blank.<br>3. Click "NEXT STEP". | An alert `"Vui lòng điền đủ thông tin!"` appears. Cannot proceed to step 2. |
| `TC_RG_02` | UI Validation | Step 2: Passwords Do Not Match | 1. Fill step 1 and proceed.<br>2. Enter "123" in Password and "124" in Confirm Password.<br>3. Click "CONTINUE". | An alert `"Mật khẩu không khớp!"` appears. Cannot proceed to step 3. |
| `TC_RG_03` | UI Validation | Step 3: Insufficient Interests | 1. Progress to step 3.<br>2. Select only 2 interests.<br>3. Click "CONTINUE". | An alert `"Chọn ít nhất 3 sở thích!"` appears. Cannot proceed to step 4. |
| `TC_RG_04` | UI Validation | Step 4: Insufficient Partner Interests | 1. Progress to step 4.<br>2. Select only 1 partner interest.<br>3. Click "FINAL STEP". | An alert `"Chọn ít nhất 3 sở thích cho họ!"` appears. Cannot proceed to step 5. |
| `TC_RG_05` | UI Validation | Step 5: Avatar Preview | 1. Progress to step 5.<br>2. Click Avatar placeholder and select an image. | The selected image is previewed inside the circle avatar wrapper. |

### Backend Processing

| Test ID | Feature | Test Scenario | Steps to Execute | Expected Result |
|---------|---------|---------------|------------------|-----------------|
| `TC_RG_06` | Backend | Successful Account Creation | 1. Fill all 5 steps with valid data.<br>2. Click "START SYNC 💖". | Alert `"Đăng ký thành công!"` appears, user is redirected to `login.html`. DB tables `users`, `profiles`, and `user_interests` are populated. Avatar is saved in `uploads/`. |
| `TC_RG_07` | Backend | Duplicate Email Attempt | 1. Use an email that already exists in DB.<br>2. Form filled completely.<br>3. Submit form. | Request fails, alert `"Email đã được đăng ký"` is shown. New account is not created. |
| `TC_RG_08` | Backend | Direct API Post without required fields | 1. Make POST request to `api/register.php` with missing email/password. | HTTP 400 response with `{"status":"error", "message":"Email, mật khẩu và điện thoại là bắt buộc"}`. |

---

## 3. User Login (`src/login.html` & `api/login.php`)

| Test ID | Feature | Test Scenario | Steps to Execute | Expected Result |
|---------|---------|---------------|------------------|-----------------|
| `TC_LG_01` | Login | Successful Login | 1. Open `login.html`.<br>2. Insert valid Email and Password.<br>3. Click "SYNC IN 💖". | Connects to DB, returns success, and redirects the user to `home.php`. |
| `TC_LG_02` | Login | Invalid Password | 1. Insert valid Email but wrong password.<br>2. Click submit. | Alert with message `"Mật khẩu không đúng!"` (from backend) is displayed. Not redirected. |
| `TC_LG_03` | Login | Unregistered Email | 1. Insert email not in database.<br>2. Click submit. | Alert with message `"Tài khoản không tồn tại!"` (from backend) is displayed. Not redirected. |
| `TC_LG_04` | UI Validation | Empty Fields | 1. Leave email or password empty.<br>2. Click submit. | HTML5 standard validation kicks in preventing form submission. |

---

## 4. Dashboard & AI Matching (`src/home.php`)

| Test ID | Feature | Test Scenario | Steps to Execute | Expected Result |
|---------|---------|---------------|------------------|-----------------|
| `TC_HM_01` | Auth Protection| Unauthenticated Access | 1. Clear sessions/cookies.<br>2. Navigate directly to `src/home.php`. | Page automatically redirects the user to `login.html` due to missing `$_SESSION['user_id']`. |
| `TC_HM_02` | Matching | Display Correct Match Algorithm Score | 1. Login with an account.<br>2. Create a secondary account with known overlapping interests.<br>3. View `home.php` of first account. | The suggested match score should calculate correctly based on formula: `50 + round((common / total_my) * 49)`. |
| `TC_HM_03` | UI Layout | Empty Matches State | 1. Ensure DB has only 1 user (the logged-in user).<br>2. Go to `home.php`. | The central feed displays "No matches found yet." The sidebar shows "Scanning". |
| `TC_HM_04` | UI Layout | Main Dashboard Cards loaded correctly | 1. Have multiple users in DB.<br>2. Login.<br>3. Go to `home.php`. | Top match shows up in the center `swipe-card` with Name, Match %, Bio, and Avatar. "Top Picks" in sidebar lists top 2 matches respectively. |
| `TC_HM_05` | UI Layout | User data loaded correctly | 1. Load `home.php`. | The active user data is queried and properly shown (if used in Header/Dashboard structure). |

---

## 5. Other Considerations

| Test ID | Feature | Test Scenario | Steps to Execute | Expected Result |
|---------|---------|---------------|------------------|-----------------|
| `TC_GN_01` | Navigation | Header and Links | 1. From `home.php`, test links for other pages (if exist via header). | Header navigates appropriately or signs the user out. |
| `TC_GN_02` | Security | XSS Checks | 1. Create a user via API with bio: `<script>alert(1)</script>`.<br>2. View the user on `home.php` as another profile. | `htmlspecialchars()` successfully parses it as text rather than executable script. No alert should pop up. |
