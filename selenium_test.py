from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.common.keys import Keys
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC
import time
import os

# --- Configuration ---
# Update this base URL to match your local server environment
BASE_URL = "http://localhost/Modeling/src"
TEST_EMAIL = "bach@abc.com"
TEST_PASSWORD = "123456"

class SoulSyncTests:
    def __init__(self):
        # Initialize Chrome WebDriver (make sure you have chromedriver installed and in PATH)
        self.driver = webdriver.Chrome()
        self.driver.maximize_window()
        self.wait = WebDriverWait(self.driver, 10)

    def save_debug_info(self, name):
        """Helper to save screenshot and page source for debugging failures."""
        try:
            filename_png = f"{name}_screenshot.png"
            filename_html = f"{name}_source.html"
            self.driver.save_screenshot(filename_png)
            with open(filename_html, "w", encoding="utf-8") as f:
                f.write(self.driver.page_source)
            print(f"[INFO] Saved debug info: {filename_png} and {filename_html} (Current URL: {self.driver.current_url})")
        except Exception as e:
            print(f"[ERROR] Failed to save debug info: {e}")

    def login_again(self):
        """Helper to re-login in case a session is expired or lost."""
        self.driver.get(f"{BASE_URL}/login.html")
        try:
            # Handle any leftover alerts from previous test cases
            try:
                alert = self.driver.switch_to.alert
                alert.accept()
            except:
                pass

            email_input = self.wait.until(EC.presence_of_element_located((By.NAME, "email")))
            password_input = self.driver.find_element(By.NAME, "password")
            login_btn = self.driver.find_element(By.CSS_SELECTOR, "button[type='submit']")
            
            email_input.clear()
            email_input.send_keys(TEST_EMAIL)
            password_input.clear()
            password_input.send_keys(TEST_PASSWORD)
            login_btn.click()
            
            self.wait.until(EC.url_contains("home.php"))
        except Exception as e:
            print(f"[ERROR] Re-login failed: {e}")

    def test_invalid_login(self):
        print("\nRunning Test Case 7: Invalid Login Credentials...")
        self.driver.get(f"{BASE_URL}/login.html")
        try:
            email_input = self.wait.until(EC.presence_of_element_located((By.NAME, "email")))
            password_input = self.driver.find_element(By.NAME, "password")
            login_btn = self.driver.find_element(By.CSS_SELECTOR, "button[type='submit']")

            email_input.send_keys("nonexistent_user_abc123@invalid.com")
            password_input.send_keys("invalid_pass_123")
            login_btn.click()
            
            # Wait for alert dialog and accept it
            alert = self.wait.until(EC.alert_is_present())
            print(f"[INFO] Invalid login alert message: {alert.text}")
            alert.accept()
            
            print("[PASS] Invalid login test passed (alert accepted).")
        except Exception as e:
            print(f"[FAIL] Invalid login test failed: {e}")
            self.save_debug_info("test_invalid_login")

    def test_login(self):
        print("\nRunning Test Case 1: User Login...")
        self.driver.get(f"{BASE_URL}/login.html")
        
        try:
            # Locate login inputs
            email_input = self.wait.until(EC.presence_of_element_located((By.NAME, "email")))
            password_input = self.driver.find_element(By.NAME, "password")
            login_btn = self.driver.find_element(By.CSS_SELECTOR, "button[type='submit']")

            # Perform login
            email_input.send_keys(TEST_EMAIL)
            password_input.send_keys(TEST_PASSWORD)
            login_btn.click()

            # Verify redirection to home.php
            self.wait.until(EC.url_contains("home.php"))
            print("[PASS] Login test passed.")
        except Exception as e:
            print(f"[FAIL] Login test failed: {e}")
            self.save_debug_info("test_login")

    def test_swipe_right_like(self):
        print("\nRunning Test Case 2: Like a Profile...")
        self.driver.get(f"{BASE_URL}/home.php")
        if "login.html" in self.driver.current_url:
            self.login_again()
            self.driver.get(f"{BASE_URL}/home.php")

        try:
            time.sleep(2)
            cards = self.driver.find_elements(By.CSS_SELECTOR, ".swipeable-card")
            if not cards:
                print("[INFO] No profile cards to swipe on. (Skipped)")
                return
            
            like_button = self.wait.until(EC.presence_of_element_located((By.CSS_SELECTOR, ".act-btn.like")))
            self.driver.execute_script("arguments[0].click();", like_button)
            time.sleep(1.5)
            
            print("[PASS] Like profile test passed.")
        except Exception as e:
            print(f"[FAIL] Like profile test failed: {e}")
            self.save_debug_info("test_swipe_right_like")

    def test_swipe_left_pass(self):
        print("\nRunning Test Case 3: Pass a Profile...")
        self.driver.get(f"{BASE_URL}/home.php")
        if "login.html" in self.driver.current_url:
            self.login_again()
            self.driver.get(f"{BASE_URL}/home.php")

        try:
            time.sleep(2)
            cards = self.driver.find_elements(By.CSS_SELECTOR, ".swipeable-card")
            if not cards:
                print("[INFO] No profile cards to pass on. (Skipped)")
                return

            close_button = self.wait.until(EC.presence_of_element_located((By.CSS_SELECTOR, ".act-btn.close")))
            self.driver.execute_script("arguments[0].click();", close_button)
            time.sleep(1.5)
            
            print("[PASS] Pass profile test passed.")
        except Exception as e:
            print(f"[FAIL] Pass profile test failed: {e}")
            self.save_debug_info("test_swipe_left_pass")

    def test_ai_icebreaker(self):
        print("\nRunning Test Case 4: AI Icebreaker Modal...")
        self.driver.get(f"{BASE_URL}/home.php")
        if "login.html" in self.driver.current_url:
            self.login_again()
            self.driver.get(f"{BASE_URL}/home.php")
        
        try:
            # Open the modal
            ai_bot_btn = self.wait.until(EC.presence_of_element_located((By.CSS_SELECTOR, ".btn-ai-bot")))
            self.driver.execute_script("arguments[0].click();", ai_bot_btn)

            # Wait for modal to open
            self.wait.until(EC.presence_of_element_located((By.CSS_SELECTOR, "#homeIcebreakerOverlay.open")))
            self.wait.until(EC.presence_of_element_located((By.ID, "homeIcebreakerSuggestions")))
            
            # Close the modal
            close_btn = self.wait.until(EC.presence_of_element_located((By.CSS_SELECTOR, ".btn-icebreaker-close")))
            self.driver.execute_script("arguments[0].click();", close_btn)
            
            print("[PASS] AI Icebreaker modal test passed.")
        except Exception as e:
            print(f"[FAIL] AI Icebreaker modal test failed: {e}")
            self.save_debug_info("test_ai_icebreaker")

    def test_explore_date_spots(self):
        print("\nRunning Test Case 5: Explore Date Spots...")
        self.driver.get(f"{BASE_URL}/explore.php")
        if "login.html" in self.driver.current_url:
            self.login_again()
            self.driver.get(f"{BASE_URL}/explore.php")
        
        try:
            romantic_tab = self.wait.until(EC.presence_of_element_located((By.XPATH, "//a[contains(@href, 'tab=romantic')]")))
            self.driver.execute_script("arguments[0].click();", romantic_tab)
            time.sleep(1)

            venues = self.driver.find_elements(By.CSS_SELECTOR, ".venue-card")
            if not venues:
                print("[INFO] No venues visible on the Romantic tab. (Skipped save testing)")
                return

            save_btn = self.wait.until(EC.presence_of_element_located((By.CSS_SELECTOR, ".venue-card .save-btn")))
            self.driver.execute_script("arguments[0].click();", save_btn)
            time.sleep(1)

            saved_tab = self.wait.until(EC.presence_of_element_located((By.XPATH, "//a[contains(@href, 'tab=saved')]")))
            self.driver.execute_script("arguments[0].click();", saved_tab)
            time.sleep(1)

            saved_venues = self.driver.find_elements(By.CSS_SELECTOR, ".venue-card")
            if saved_venues:
                print("[PASS] Verified venue is listed under 'Saved' tab.")
                unsave_btn = self.wait.until(EC.presence_of_element_located((By.CSS_SELECTOR, ".venue-card .save-btn")))
                self.driver.execute_script("arguments[0].click();", unsave_btn)
                time.sleep(1)
            else:
                print("[FAIL] Venue was not saved or is not listed.")

            print("[PASS] Explore Date Spots test completed.")
        except Exception as e:
            print(f"[FAIL] Explore Date Spots test failed: {e}")
            self.save_debug_info("test_explore_date_spots")

    def test_view_date_spot_detail(self):
        print("\nRunning Test Case 8: View Date Spot Detail...")
        self.driver.get(f"{BASE_URL}/explore.php")
        if "login.html" in self.driver.current_url:
            self.login_again()
            self.driver.get(f"{BASE_URL}/explore.php")
        try:
            # Locate "View Details" button using title attribute
            details_btn = self.wait.until(EC.presence_of_element_located((By.XPATH, "//button[@title='View venue details']")))
            self.driver.execute_script("arguments[0].click();", details_btn)
            
            # Expect redirection to date_spot_detail.php
            self.wait.until(EC.url_contains("date_spot_detail.php"))
            print("[PASS] View Date Spot Detail test passed.")
        except Exception as e:
            print(f"[FAIL] View Date Spot Detail test failed: {e}")
            self.save_debug_info("test_view_date_spot_detail")

    def test_toggle_chat_modes(self):
        print("\nRunning Test Case 10: Toggle Chat Modes...")
        self.driver.get(f"{BASE_URL}/messages.php")
        if "login.html" in self.driver.current_url:
            self.login_again()
            self.driver.get(f"{BASE_URL}/messages.php")
        try:
            # Find the "Blind Mode" toggle button robustly by text
            blind_mode_btn = self.wait.until(EC.presence_of_element_located((By.XPATH, "//button[contains(., 'Blind Mode')]")))
            self.driver.execute_script("arguments[0].click();", blind_mode_btn)
            
            self.wait.until(EC.url_contains("mode=blind"))
            print("[PASS] Toggle Chat Modes test passed.")
        except Exception as e:
            print(f"[FAIL] Toggle Chat Modes test failed: {repr(e)}")
            self.save_debug_info("test_toggle_chat_modes")

    def test_queue_blind_date(self):
        print("\nRunning Test Case 11: Queue and Cancel Blind Date...")
        self.driver.get(f"{BASE_URL}/messages.php?mode=blind")
        if "login.html" in self.driver.current_url:
            self.login_again()
            self.driver.get(f"{BASE_URL}/messages.php?mode=blind")
        try:
            # Locate button with id 'btnBlindDate'
            btn_blind = self.wait.until(EC.presence_of_element_located((By.ID, "btnBlindDate")))
            
            btn_text = btn_blind.text
            if "Scanning" in btn_text:
                # Cancel it first to have a clean start
                self.driver.execute_script("arguments[0].click();", btn_blind)
                time.sleep(1)
                btn_blind = self.wait.until(EC.presence_of_element_located((By.ID, "btnBlindDate")))
            
            # Click to Queue
            self.driver.execute_script("arguments[0].click();", btn_blind)
            
            # Wait for button text to contain "Scanning"
            self.wait.until(lambda d: "Scanning" in d.find_element(By.ID, "btnBlindDate").text)
            print("[PASS] Entered Blind Date Queue successfully.")
            
            # Now cancel it
            updated_btn = self.driver.find_element(By.ID, "btnBlindDate")
            self.driver.execute_script("arguments[0].click();", updated_btn)
            
            # Wait for button text to revert to "New Blind Date"
            self.wait.until(lambda d: "New Blind Date" in d.find_element(By.ID, "btnBlindDate").text)
            print("[PASS] Left Blind Date Queue successfully.")
        except Exception as e:
            print(f"[FAIL] Queue Blind Date test failed: {repr(e)}")
            self.save_debug_info("test_queue_blind_date")

    def test_chat_messaging(self):
        print("\nRunning Test Case 9: Chat Navigation and Messaging...")
        self.driver.get(f"{BASE_URL}/messages.php?mode=standard")
        if "login.html" in self.driver.current_url:
            self.login_again()
            self.driver.get(f"{BASE_URL}/messages.php?mode=standard")
        try:
            # Look for active chat threads
            threads = self.driver.find_elements(By.CSS_SELECTOR, ".chat-threads a.chat-thread")
            if not threads:
                print("[INFO] No active matches/chat threads to test messaging. (Skipped)")
                return
            
            self.driver.execute_script("arguments[0].click();", threads[0])
            time.sleep(1.5)
            
            # Locate message input
            msg_input = self.wait.until(EC.presence_of_element_located((By.ID, "msgInput")))
            send_btn = self.driver.find_element(By.CSS_SELECTOR, "button.btn-send")
            
            test_msg = f"Automated test message sent at {time.strftime('%X')}"
            msg_input.send_keys(test_msg)
            self.driver.execute_script("arguments[0].click();", send_btn)
            time.sleep(2)
            
            print("[PASS] Chat Navigation and Messaging test completed.")
        except Exception as e:
            print(f"[FAIL] Chat Navigation and Messaging test failed: {e}")
            self.save_debug_info("test_chat_messaging")

    def test_edit_profile(self):
        print("\nRunning Test Case 6: Edit Profile Info...")
        self.driver.get(f"{BASE_URL}/edit_profile.php")
        if "login.html" in self.driver.current_url:
            self.login_again()
            self.driver.get(f"{BASE_URL}/edit_profile.php")
        
        try:
            # Locate nickname and bio fields
            nickname_input = self.wait.until(EC.presence_of_element_located((By.NAME, "nickname")))
            bio_textarea = self.driver.find_element(By.NAME, "bio")
            save_btn = self.driver.find_element(By.CSS_SELECTOR, "button.btn-save-changes")

            new_nickname = "Bach Automated"
            new_bio = f"Automated test bio updated at {time.strftime('%X')}"

            # Set input values directly using JS value assignment
            self.driver.execute_script("arguments[0].value = arguments[1];", nickname_input, new_nickname)
            self.driver.execute_script("arguments[0].value = arguments[1];", bio_textarea, new_bio)
            
            # Dispatch event so browser registers value change
            self.driver.execute_script("arguments[0].dispatchEvent(new Event('input', { bubbles: true }));", nickname_input)
            self.driver.execute_script("arguments[0].dispatchEvent(new Event('change', { bubbles: true }));", nickname_input)
            self.driver.execute_script("arguments[0].dispatchEvent(new Event('input', { bubbles: true }));", bio_textarea)
            self.driver.execute_script("arguments[0].dispatchEvent(new Event('change', { bubbles: true }));", bio_textarea)
            
            time.sleep(0.5)

            # Debug values in browser before saving
            print(f"[DEBUG] Nickname in browser DOM before save: '{nickname_input.get_attribute('value')}'")
            print(f"[DEBUG] Bio in browser DOM before save: '{bio_textarea.get_attribute('value')}'")

            # Save Changes
            self.driver.execute_script("arguments[0].click();", save_btn)

            # Expect redirection to profile.php
            self.wait.until(EC.url_contains("profile.php"))

            # Verify updated info on profile.php
            profile_name = self.wait.until(EC.presence_of_element_located((By.CSS_SELECTOR, ".profile-info-main h1"))).text
            profile_bio = self.driver.find_element(By.CSS_SELECTOR, ".profile-info-main .bio-quote").text

            if new_nickname in profile_name and new_bio in profile_bio:
                print("[PASS] Profile info updated and verified successfully.")
            else:
                print(f"[FAIL] Updated info does not match. Found Name: {profile_name}, Bio: {profile_bio}")
                self.save_debug_info("test_edit_profile")

        except Exception as e:
            print(f"[FAIL] Edit Profile test failed: {e}")
            self.save_debug_info("test_edit_profile")

    def test_logout(self):
        print("\nRunning Test Case 12: Logout Session...")
        self.driver.get(f"{BASE_URL}/profile.php")
        if "login.html" in self.driver.current_url:
            self.login_again()
            self.driver.get(f"{BASE_URL}/profile.php")
        try:
            # Locate logout button
            logout_btn = self.wait.until(EC.presence_of_element_located((By.XPATH, "//button[contains(text(), 'LOG OUT')]")))
            self.driver.execute_script("arguments[0].click();", logout_btn)
            
            # Verify redirected to login.html
            self.wait.until(EC.url_contains("login.html"))
            print("[PASS] Logout test passed.")
        except Exception as e:
            print(f"[FAIL] Logout test failed: {e}")
            self.save_debug_info("test_logout")

    def run_all(self):
        try:
            # Test Case 7: Invalid Login (Runs first before any session is set)
            self.test_invalid_login()
            
            # Test Case 1: Valid Login (Establishes session)
            self.test_login()
            
            # Feed & Swiping tests
            self.test_swipe_right_like()
            self.test_swipe_left_pass()
            self.test_ai_icebreaker()
            
            # Venue Exploration & Detail tests
            self.test_explore_date_spots()
            self.test_view_date_spot_detail()
            
            # Messaging / Mode toggles / Queueing tests
            self.test_toggle_chat_modes()
            self.test_queue_blind_date()
            self.test_chat_messaging()
            
            # Profile & session tests
            self.test_edit_profile()
            self.test_logout()
            
        finally:
            print("\nTests completed. Closing browser.")
            self.driver.quit()

if __name__ == "__main__":
    tests = SoulSyncTests()
    tests.run_all()
