# BRGY. HEALTH CENTER RECORD KEEPING SYSTEM

Programmed by: Dela Cruz, Lyn R.

## Setup (XAMPP)
1. Put the folder in `htdocs/brgy_health`. Start Apache and MySQL.
2. phpMyAdmin → Import → `database.sql` (just one file).
3. Open `http://localhost/brgy_health/login.php`. First login: `admin` / `admin123` (you will be forced to change the password).
4. Admin → **Demo** → "Load demo data" to get sample data.
5. (Optional) SMS: put the Semaphore API key in `sms_config.php` and set `SMS_ENABLED` to `true`.

## Resident registration
Residents can register at `register.php` (linked from the login page) using their name, birthday, username, and password (the child's name and birthday are optional). The system looks for a record with the same name and birthday, but the account stays **pending** until the Admin approves it on the **Users** page after verifying the valid ID. Pending or rejected accounts cannot log in.

## Resident portal features
- **Valid ID upload:** at registration and in the Portal (JPG/PNG/PDF, up to 2MB). Files are stored in `uploads/ids/` (they cannot be opened by URL; only through `view_id.php`, for the owner and staff).
- **Residents (staff):** menu **👪 Residents** → list of accounts → open one to view the ID, verify or reject it, edit the account, reset the password, link a patient, and see appointments. Admin, Doctor, Nurse, and Midwife can edit; BHW is view-only (their purok).
- **Residents can no longer edit what staff has edited:** when staff changes a patient's contact or address, it is locked (🔒). Only the Admin can unlock it on the patient's profile.
- **Appointments:** include "Purpose" (Vaccination, Prenatal, BP/Sugar, Consultation, Medical certificate, Other) and "Reason / details" visible to staff.
- **Look and feel:** larger text, health theme, and the Barangay Quirino logo as the background (`assets/bg.jpg`; replace the image to change the background).
- Make sure XAMPP can write to the `uploads/ids/` folder. If uploads are rejected, check `upload_max_filesize` in `php.ini` (the default of 2M matches).

## Roles
Admin, Doctor, Nurse, Midwife (all patients), BHW (their purok only), Resident (own record and their child's, medical data is read-only).

## Demo accounts
| Role | Username / Password |
|---|---|
| Admin | admin / (your new password) |
| Doctor | doctor1 / doctor123 |
| Nurse | nurse1 / nurse1234 |
| Midwife | midwife1 / midwife123 |
| BHW (Purok 1) | bhw1 / bhw12345 |
| Resident | resident1 / resident123 |

## 5-minute demo
1. Admin: Dashboard (overdue, high BP, near delivery).
2. Patients → Baby Ana Reyes: red **allergy** warning; vaccine schedule (Overdue) → "Given" (shows an allergy warning) → see **which nurse/doctor** gave it → QR card.
3. Lolo Pedro Ramos: BP/sugar log, red flag, and who took the reading.
4. Rosa Mendoza: EDD, trimester, number of prenatal visits, and who attended.
5. Appointments: assign and confirm Baby Juan's request to a nurse.
6. Register a new resident at register.php (enter Baby Juan Santos's name and birthday), then approve on the Users page.
7. Admin → 👪 Residents: open the new registration, view the ID, verify it (the account is approved automatically).
8. Log in as resident1: Portal (including the child's allergy), 🔔 notices, change the contact.
9. bhw1: sees Purok 1 only.
10. Admin: Reports (including vaccines given by each health worker), Audit Log, SMS page.

## Security in place
Hashed passwords, role-based access (checked on the server), CSRF token, prepared statements (PDO), HttpOnly cookie, audit log with user, and the default admin password must be changed.

## Limitations (state these honestly at the defense)
- No login attempt limit and no HTTPS set up.
- No appointment slot limit and no vaccine/medicine inventory.
- The system only reminds about allergies; the health worker decides whether a vaccine is safe.
- SMS via Semaphore (paid credits); no retry for failed SMS.
- The vaccine schedule follows the usual DOH EPI; it should be verified by the midwife.

## If you already have data (upgrade the old database, do not drop it)
```sql
USE brgy_health;
ALTER TABLE users ADD COLUMN status ENUM('active','pending','rejected') NOT NULL DEFAULT 'active' AFTER role;
ALTER TABLE users ADD COLUMN full_name VARCHAR(120) NOT NULL DEFAULT '' AFTER password_hash,
 ADD COLUMN license_no VARCHAR(30) NULL,
 MODIFY role ENUM('admin','doctor','nurse','midwife','bhw','resident') NOT NULL;
UPDATE users SET full_name=username WHERE full_name='';
ALTER TABLE vaccinations ADD COLUMN given_by INT NULL, ADD FOREIGN KEY(given_by) REFERENCES users(id) ON DELETE SET NULL;
ALTER TABLE vitals ADD COLUMN taken_by INT NULL, ADD FOREIGN KEY(taken_by) REFERENCES users(id) ON DELETE SET NULL;
ALTER TABLE prenatal_visits ADD COLUMN attended_by INT NULL, ADD FOREIGN KEY(attended_by) REFERENCES users(id) ON DELETE SET NULL;
ALTER TABLE appointments ADD COLUMN assigned_to INT NULL, ADD FOREIGN KEY(assigned_to) REFERENCES users(id) ON DELETE SET NULL;
ALTER TABLE patients ADD COLUMN contact_locked TINYINT(1) NOT NULL DEFAULT 0, ADD COLUMN address_locked TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE appointments ADD COLUMN notes VARCHAR(255) NULL AFTER purpose;
CREATE TABLE resident_ids(
 id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, id_type VARCHAR(40) NOT NULL,
 file_front VARCHAR(80) NOT NULL, file_back VARCHAR(80) NULL,
 status ENUM('Pending','Verified','Rejected') NOT NULL DEFAULT 'Pending', remarks VARCHAR(150) NULL,
 uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE);
CREATE TABLE patient_allergies(
 id INT AUTO_INCREMENT PRIMARY KEY, patient_id INT NOT NULL, allergen VARCHAR(100) NOT NULL, reaction VARCHAR(150),
 severity ENUM('Mild','Moderate','Severe') NOT NULL DEFAULT 'Mild', noted_date DATE,
 FOREIGN KEY(patient_id) REFERENCES patients(id) ON DELETE CASCADE);
```
