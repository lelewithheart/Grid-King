#!/usr/bin/env python3
import requests
import sys
from bs4 import BeautifulSoup
import re

class RacingLeagueAPITester:
    def __init__(self, base_url="https://607ad625-0bbb-49f8-9a08-186d4609ed96.preview.emergentagent.com"):
        self.base_url = base_url
        self.session = requests.Session()
        self.tests_run = 0
        self.tests_passed = 0
        self.csrf_token = None

    def run_test(self, name, method, endpoint, expected_status=200, data=None, check_content=None, extract_csrf=False):
        """Run a single API test"""
        url = f"{self.base_url}/{endpoint}"
        
        self.tests_run += 1
        print(f"\n🔍 Testing {name}...")
        
        try:
            if method == 'GET':
                response = self.session.get(url)
            elif method == 'POST':
                response = self.session.post(url, data=data)
            
            # Check status code
            status_success = response.status_code == expected_status
            
            # Extract CSRF token if needed
            if extract_csrf and status_success:
                soup = BeautifulSoup(response.text, 'html.parser')
                csrf_input = soup.find('input', {'name': 'csrf_token'})
                if csrf_input:
                    self.csrf_token = csrf_input.get('value')
                    print(f"✅ Extracted CSRF token: {self.csrf_token}")
                else:
                    print("⚠️ Could not find CSRF token")
            
            # Check content if specified
            content_success = True
            if check_content and status_success:
                if isinstance(check_content, str):
                    content_success = check_content in response.text
                elif callable(check_content):
                    content_success = check_content(response.text)
            
            success = status_success and content_success
            
            if success:
                self.tests_passed += 1
                print(f"✅ Passed - Status: {response.status_code}")
            else:
                if not status_success:
                    print(f"❌ Failed - Expected status {expected_status}, got {response.status_code}")
                if not content_success:
                    print(f"❌ Failed - Content check failed")
            
            return success, response
        
        except Exception as e:
            print(f"❌ Failed - Error: {str(e)}")
            return False, None

    def test_homepage(self):
        """Test if homepage loads correctly"""
        return self.run_test(
            "Homepage",
            "GET",
            "",
            expected_status=200,
            check_content="Racing League Management System"
        )

    def test_login_page(self):
        """Test if login page loads correctly and extract CSRF token"""
        return self.run_test(
            "Login Page",
            "GET",
            "login.php",
            expected_status=200,
            check_content="Login to Racing League",
            extract_csrf=True
        )

    def test_login(self, email, password):
        """Test login functionality"""
        data = {
            'email': email,
            'password': password,
            'csrf_token': self.csrf_token
        }
        
        success, response = self.run_test(
            f"Login as {email}",
            "POST",
            "login.php",
            expected_status=200,
            data=data,
            check_content=lambda text: "dashboard.php" in text or "admin/dashboard.php" in text
        )
        
        if success:
            # Check if we're redirected to dashboard or admin dashboard
            if response.url.endswith('dashboard.php'):
                print("✅ Successfully logged in as regular user")
            elif 'admin/dashboard.php' in response.url:
                print("✅ Successfully logged in as admin")
            else:
                print("⚠️ Login succeeded but unexpected redirect")
                success = False
        
        return success

    def test_standings_page(self):
        """Test if standings page loads correctly"""
        success, response = self.run_test(
            "Standings Page",
            "GET",
            "standings.php",
            expected_status=200,
            check_content="Championship Standings"
        )
        
        if success:
            # Check if john_racer is in 1st place with 26 points
            soup = BeautifulSoup(response.text, 'html.parser')
            standings_table = soup.find('table', {'id': 'driver-standings'})
            
            if standings_table:
                first_row = standings_table.find('tbody').find('tr')
                if first_row:
                    cells = first_row.find_all('td')
                    if len(cells) >= 3:
                        driver_name = cells[1].text.strip()
                        points = cells[2].text.strip()
                        
                        if 'john_racer' in driver_name.lower() and '26' in points:
                            print("✅ Verified john_racer is in 1st place with 26 points")
                        else:
                            print(f"⚠️ Expected john_racer with 26 points, found {driver_name} with {points}")
                            success = False
                    else:
                        print("⚠️ Standings table has unexpected structure")
                        success = False
                else:
                    print("⚠️ No rows found in standings table")
                    success = False
            else:
                print("⚠️ Could not find driver standings table")
                success = False
        
        return success

    def test_admin_dashboard(self):
        """Test if admin dashboard loads correctly"""
        return self.run_test(
            "Admin Dashboard",
            "GET",
            "admin/dashboard.php",
            expected_status=200,
            check_content="Admin Dashboard"
        )

    def test_admin_results_page(self):
        """Test if admin results page loads correctly"""
        return self.run_test(
            "Admin Results Page",
            "GET",
            "admin/results.php",
            expected_status=200,
            check_content="Enter Race Results"
        )

    def test_submit_race_results(self, race_id=2):
        """Test submitting race results"""
        # First, get the results page to extract form fields
        success, response = self.run_test(
            "Get Results Form",
            "GET",
            f"admin/results.php?race_id={race_id}",
            expected_status=200,
            check_content="Enter Race Results"
        )
        
        if not success:
            return False
        
        # Extract CSRF token and driver IDs
        soup = BeautifulSoup(response.text, 'html.parser')
        csrf_input = soup.find('input', {'name': 'csrf_token'})
        
        if not csrf_input:
            print("⚠️ Could not find CSRF token for results submission")
            return False
        
        csrf_token = csrf_input.get('value')
        
        # Find driver inputs
        driver_inputs = soup.find_all('input', {'name': re.compile(r'position\[\d+\]')})
        driver_ids = []
        
        for input_field in driver_inputs:
            name = input_field.get('name')
            match = re.search(r'position\[(\d+)\]', name)
            if match:
                driver_ids.append(match.group(1))
        
        if not driver_ids:
            print("⚠️ Could not find driver inputs for results submission")
            return False
        
        # Prepare data for submission
        data = {
            'race_id': race_id,
            'csrf_token': csrf_token
        }
        
        # Assign positions to drivers
        for i, driver_id in enumerate(driver_ids):
            data[f'position[{driver_id}]'] = i + 1
        
        # Submit results
        success, response = self.run_test(
            "Submit Race Results",
            "POST",
            "admin/results.php",
            expected_status=200,
            data=data,
            check_content="Results saved successfully"
        )
        
        return success

    def test_driver_dashboard(self):
        """Test if driver dashboard loads correctly"""
        return self.run_test(
            "Driver Dashboard",
            "GET",
            "dashboard.php",
            expected_status=200,
            check_content="Driver Dashboard"
        )

    def test_register_page(self):
        """Test if register page loads correctly"""
        return self.run_test(
            "Register Page",
            "GET",
            "register.php",
            expected_status=200,
            check_content="Register for Racing League",
            extract_csrf=True
        )

    def print_summary(self):
        """Print test summary"""
        print("\n" + "="*50)
        print(f"📊 Tests Summary: {self.tests_passed}/{self.tests_run} passed")
        print("="*50)
        
        if self.tests_passed == self.tests_run:
            print("✅ All tests passed!")
            return 0
        else:
            print(f"❌ {self.tests_run - self.tests_passed} tests failed")
            return 1

def main():
    tester = RacingLeagueAPITester()
    
    # Test basic pages
    tester.test_homepage()
    tester.test_login_page()
    
    # Test admin login and functionality
    if tester.test_login("admin@racingleague.com", "admin123"):
        tester.test_admin_dashboard()
        tester.test_admin_results_page()
        tester.test_submit_race_results(race_id=2)
    
    # Test standings page
    tester.test_standings_page()
    
    # Test driver login and dashboard
    tester.test_login_page()
    if tester.test_login("john_racer@example.com", "admin123"):
        tester.test_driver_dashboard()
    
    # Test registration page
    tester.test_register_page()
    
    # Print summary and return exit code
    return tester.print_summary()

if __name__ == "__main__":
    sys.exit(main())