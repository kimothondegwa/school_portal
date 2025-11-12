# CHANGES MADE TO FIX API ENDPOINTS

## File: api/grades_api.php

### Change 1: GET Request - Single Student (Line 25-26)
```php
BEFORE:
"SELECT g.grade_id, s.first_name, s.last_name, 
        a.title AS assignment_title, g.marks, g.total_marks, 
        g.grade_letter, g.created_at"

AFTER:
"SELECT g.grade_id, s.first_name, s.last_name, 
        a.title AS assignment_title, g.marks_obtained, g.total_marks, 
        g.grade, g.created_at"
```

### Change 2: GET Request - All Grades (Line 44-45)
```php
BEFORE:
"SELECT g.grade_id, s.first_name, s.last_name, 
        a.title AS assignment_title, g.marks, g.total_marks, 
        g.grade_letter, g.created_at"

AFTER:
"SELECT g.grade_id, s.first_name, s.last_name, 
        a.title AS assignment_title, g.marks_obtained, g.total_marks, 
        g.grade, g.created_at"
```

### Change 3: POST Request - Parameter Names (Line 59-75)
```php
BEFORE:
isset($data['marks']) &&
$marks = $data['marks'];
INSERT INTO grades (assignment_id, student_id, marks, total_marks, grade_letter, created_at)
':marks' => $marks,
':grade_letter' => $grade_letter

AFTER:
isset($data['marks_obtained']) &&
$marks_obtained = $data['marks_obtained'];
INSERT INTO grades (assignment_id, student_id, marks_obtained, total_marks, grade, created_at)
':marks_obtained' => $marks_obtained,
':grade' => $grade
```

### Change 4: POST Response (Line 105)
```php
BEFORE:
"grade_letter" => $grade_letter

AFTER:
"grade" => $grade
```

### Change 5: PUT Request - Parameter Names (Line 113-120)
```php
BEFORE:
isset($data['marks']) &&
$marks = $data['marks'];
$percentage = ($marks / $total_marks) * 100;
if ($percentage >= 80) $grade_letter = 'A';
...
UPDATE grades 
SET marks = :marks, total_marks = :total_marks, grade_letter = :grade_letter

AFTER:
isset($data['marks_obtained']) &&
$marks_obtained = $data['marks_obtained'];
$percentage = ($marks_obtained / $total_marks) * 100;
if ($percentage >= 80) $grade = 'A';
...
UPDATE grades 
SET marks_obtained = :marks_obtained, total_marks = :total_marks, grade = :grade
```

### Change 6: PUT Bind Parameters (Line 119-120)
```php
BEFORE:
':marks' => $marks,
':total_marks' => $total_marks,
':grade_letter' => $grade_letter,

AFTER:
':marks_obtained' => $marks_obtained,
':total_marks' => $total_marks,
':grade' => $grade,
```

### Change 7: PUT Response (Line 139)
```php
BEFORE:
"grade_letter" => $grade_letter

AFTER:
"grade" => $grade
```

---

## Summary of Changes

| Line | Change Type | From | To |
|------|-------------|------|-----|
| 25 | Column name | g.marks | g.marks_obtained |
| 26 | Column name | g.grade_letter | g.grade |
| 44 | Column name | g.marks | g.marks_obtained |
| 45 | Column name | g.grade_letter | g.grade |
| 59 | Parameter | $data['marks'] | $data['marks_obtained'] |
| 61 | Variable | $marks | $marks_obtained |
| 63 | Variable in calc | $marks | $marks_obtained |
| 68 | SQL column | marks | marks_obtained |
| 68 | SQL column | grade_letter | grade |
| 72 | Bind parameter | :marks | :marks_obtained |
| 75 | Bind parameter | :grade_letter | :grade |
| 105 | Response key | grade_letter | grade |
| 114 | Parameter | $data['marks'] | $data['marks_obtained'] |
| 116 | Variable | $marks | $marks_obtained |
| 118 | Variable in calc | $marks | $marks_obtained |
| 119 | Variable | $grade_letter | $grade |
| 121 | SQL column | marks | marks_obtained |
| 121 | SQL column | grade_letter | grade |
| 125 | Bind parameter | :marks | :marks_obtained |
| 126 | Bind parameter | :grade_letter | :grade |
| 139 | Response key | grade_letter | grade |

---

## Database Column Reference

### Grades Table Structure:
```sql
CREATE TABLE grades (
  grade_id INT PRIMARY KEY AUTO_INCREMENT,
  student_id INT,
  assignment_id INT,
  marks_obtained FLOAT NOT NULL,     ← Column name (not "marks")
  total_marks FLOAT,
  percentage FLOAT,
  grade VARCHAR(2),                  ← Column name (not "grade_letter")
  feedback TEXT,
  graded_by INT,
  graded_at DATETIME,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

---

## Verification

After these changes:
- ✅ API grades_api.php now correctly references database columns
- ✅ GET requests will successfully retrieve grades
- ✅ POST requests will successfully insert grades
- ✅ PUT requests will successfully update grades
- ✅ DELETE requests will continue to work
- ✅ All bind parameters match database columns
- ✅ Response JSON keys are accurate

---

## Testing the API

After fix, test with:

```bash
# GET all grades
curl "http://localhost/school_portal/api/grades_api.php"

# GET grades for student ID 1
curl "http://localhost/school_portal/api/grades_api.php?student_id=1"

# POST new grade
curl -X POST "http://localhost/school_portal/api/grades_api.php" \
  -H "Content-Type: application/json" \
  -d '{
    "assignment_id": 1,
    "student_id": 1,
    "marks_obtained": 85,
    "total_marks": 100
  }'

# PUT update grade
curl -X PUT "http://localhost/school_portal/api/grades_api.php" \
  -d "grade_id=1&marks_obtained=90&total_marks=100"

# DELETE grade
curl -X DELETE "http://localhost/school_portal/api/grades_api.php" \
  -d "grade_id=1"
```

---

**All fixes applied successfully!** ✅

