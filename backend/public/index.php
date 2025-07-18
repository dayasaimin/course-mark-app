<?php
require __DIR__ . '/../vendor/autoload.php';

require __DIR__ . '/../includes/db.php';
use Slim\Factory\AppFactory;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Middleware\BodyParsingMiddleware;


$app = AppFactory::create();
$app->addBodyParsingMiddleware();



// try dulu localhost:8085/hello
$app->get('/hello', function ($request, $response) {
    $response->getBody()->write(json_encode(['message' => 'Hello, world']));
    return $response->withHeader('Content-Type', 'application/json');
});



/*
    Ini adalah untuk role lecturer ----------------------------------------------------------------------------
*/

// feature 1) - course management list ================================

//GET all course utk display di  http://localhost:8085/course

$app->get('/course', function ($request, $response) {
$pdo = getPDO();
$stmt = $pdo->query("SELECT * FROM course_list");
$data = $stmt->fetchAll();

$response->getBody()->write(json_encode($data));
return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
});


// POST create course - wujudkan course 

$app->post('/course', function ($request, $response) {
    try {
        $pdo = getPDO();

        // Parse JSON input from request body
         $data = json_decode($request->getBody()->getContents(), true);

        // Extract and validate fields
        $CourseCode = $data['CourseCode'] ?? null;
        $CourseName = $data['CourseName'] ?? null;
        $CreditHours = $data['CreditHours'] ?? null;
        

        // Simple backend validation
        if (!$CourseCode || !$CourseName || !$CreditHours ) {
            $response->getBody()->write(json_encode(["error" => "Missing required fields"]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        // Insert into the database
        $stmt = $pdo->prepare("
            INSERT INTO course_list (CourseCode, CourseName, CreditHours)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$CourseCode, $CourseName, $CreditHours]);

        // Response
        $response->getBody()->write(json_encode([
            "message" => "Course created successfully",
            'data' => $data
        ]));
        return $response->withStatus(201)->withHeader('Content-Type', 'application/json');
    } catch (PDOException $e) {
        // Handle DB errors
        $response->getBody()->write(json_encode([
            "error" => "Database error",
            "details" => $e->getMessage()
        ]));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
});

// edit course - untuk edit course 

$app->put('/course/{CourseCode}', function ($request, $response, $args) {
    $pdo = getPDO();
    $CourseCode = $args['CourseCode'];
    $data = json_decode($request->getBody()->getContents(), true);

    $CourseName = $data['CourseName'] ?? '';
    $CreditHours = $data['CreditHours'] ?? '';

    if (!$CourseName || !$CreditHours) {
        $response->getBody()->write(json_encode([
            'success' => false,
            'message' => 'Sila isi semua ruangan'
        ]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }

    try {
        $stmt = $pdo->prepare("UPDATE course_list SET CourseName = ?, CreditHours = ? WHERE CourseCode = ?");
        $stmt->execute([$CourseName, $CreditHours, $CourseCode]);

        $response->getBody()->write(json_encode([
            'success' => true,
            'message' => 'Course berjaya dikemaskini'
        ]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    } catch (PDOException $e) {
        $response->getBody()->write(json_encode([
            'success' => false,
            'message' => 'Gagal kemaskini course',
            'details' => $e->getMessage()
        ]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }
});

//delete course - untuk padam rekod course

$app->delete('/course/{CourseCode}', function ($request, $response, $args) {
    $pdo = getPDO();
    $CourseCode = $args['CourseCode'];

    try {
        $stmt = $pdo->prepare("DELETE FROM course_list WHERE CourseCode = ?");
        $stmt->execute([$CourseCode]);

        $response->getBody()->write(json_encode([
            'success' => true,
            'message' => 'Course berjaya dipadam'
        ]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    } catch (PDOException $e) {
        $response->getBody()->write(json_encode([
            'success' => false,
            'message' => 'Gagal padam course',
            'details' => $e->getMessage()
        ]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }
});







// feature 2) student management list-------------------------------

//view all student - utk paparkan di http://localhost:8085/students
$app->get('/students', function ($request, $response) {
    $pdo = getPDO();
    $stmt = $pdo->query("SELECT * FROM student_list");
    $data = $stmt->fetchAll();

    $response->getBody()->write(json_encode($data));
    return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
});


// POST create student - tak benarkan student mendaftar subjek yg sama lebih dari satu kali.. 
// satu student boleh mendaftar lebih dari satu course.

$app->post('/students', function ($request, $response) {
    try {
        $pdo = getPDO();
        $data = json_decode($request->getBody()->getContents(), true);

        $studentID = $data['studentID'] ?? null;
        $studentName = $data['studentName'] ?? null;
        $studentEmail = $data['studentEmail'] ?? null;
        $CourseCode = $data['CourseCode'] ?? null; 

        if (!$studentID || !$studentName || !$studentEmail || !$CourseCode) {
            $response->getBody()->write(json_encode(["error" => "Missing required fields"]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        // ✅ 1. Insert student ke dalam table student_list (jika belum wujud)
        $stmt = $pdo->prepare("SELECT * FROM student_list WHERE studentID = ?");
        $stmt->execute([$studentID]);

        if ($stmt->rowCount() === 0) {
            $stmt = $pdo->prepare("
                INSERT INTO student_list (studentID, studentName, studentEmail)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$studentID, $studentName, $studentEmail]);
        }

        // ✅ 2. Check if student already registered for this course
        $checkStmt = $pdo->prepare("SELECT * FROM student_course WHERE studentID = ? AND CourseCode = ?");
        $checkStmt->execute([$studentID, $CourseCode]);

        if ($checkStmt->rowCount() > 0) {
            // Sudah daftar course ini
            $response->getBody()->write(json_encode([
                "error" => "Pelajar ini sudah didaftarkan untuk kursus ini"
            ]));
            return $response->withStatus(409)->withHeader('Content-Type', 'application/json');
        }

        // ✅ 3. Register student to course
        $stmt2 = $pdo->prepare("INSERT INTO student_course (CourseCode, studentID) VALUES (?, ?)");
        $stmt2->execute([$CourseCode, $studentID]);

        $response->getBody()->write(json_encode([
            "message" => "Student and course registered successfully",
            "data" => $data
        ]));
        return $response->withStatus(201)->withHeader('Content-Type', 'application/json');

    } catch (PDOException $e) {
        $response->getBody()->write(json_encode([
            "error" => "Database error",
            "details" => $e->getMessage()
        ]));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
});



// view all students  - lepas create nak display all student dekat frontend (view all student list)
// boleh juga tengok di utk paparkan di http://localhost:8085/viewstudents

$app->get('/viewstudents', function ($request, $response) {
    try {
        $pdo = getPDO();
        $stmt = $pdo->query("SELECT * FROM view_student_course");
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json')
                        ->withHeader('Access-Control-Allow-Origin', '*')
                        ->withStatus(200);
    } catch (PDOException $e) {
        $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
        return $response->withHeader('Content-Type', 'application/json')
                        ->withHeader('Access-Control-Allow-Origin', '*')
                        ->withStatus(500);
    }
});

// lecturer edit modal box utk student 
$app->put('/students/{studentID}', function ($request, $response, $args) {
    try {
        $pdo = getPDO();
        $studentID = $args['studentID'];
        $data = json_decode($request->getBody()->getContents(), true);

        $studentName = $data['studentName'] ?? null;
        $studentEmail = $data['studentEmail'] ?? null;

        if (!$studentName || !$studentEmail) {
            return $response->withJson(['error' => 'Missing name or email'], 400);
        }

        $stmt = $pdo->prepare("UPDATE student_list SET studentName = ?, studentEmail = ? WHERE studentID = ?");
        $stmt->execute([$studentName, $studentEmail, $studentID]);

        return $response->withJson(['message' => 'Student updated successfully']);
    } catch (PDOException $e) {
        return $response->withJson([
            'error' => 'Database error',
            'details' => $e->getMessage()
        ], 500);
    }
});


// delete student dalam course tertentu (ibarat student drop course)
$app->delete('/students/{studentID}/course/{courseCode}', function ($request, $response, $args) {
    try {
        $pdo = getPDO();
        $studentID = $args['studentID'];
        $CourseCode = $args['CourseCode'];

        $stmt = $pdo->prepare("DELETE FROM student_course WHERE studentID = ? AND CourseCode = ?");
        $stmt->execute([$studentID, $CourseCode]);

        if ($stmt->rowCount() === 0) {
            return $response->withJson(['error' => 'No matching student-course found'], 404);
        }

        return $response->withJson(['message' => 'Student removed from course']);
    } catch (PDOException $e) {
        return $response->withJson([
            'error' => 'Database error',
            'details' => $e->getMessage()
        ], 500);
    }
});




//lecturer signup 

$app->post('/signup', function ($request, $response) {
    $pdo = getPDO();

    $uploadedFiles = $request->getUploadedFiles();
    $data = $request->getParsedBody();

    $full_name = $data['full_name'] ?? '';
    $username = $data['username'] ?? '';
    $email = $data['email'] ?? '';
    $password = $data['password'] ?? '';
    $role = $data['role'] ?? '';

    $imageName = 'default.jpg'; 

    if (isset($uploadedFiles['image'])) {
        $image = $uploadedFiles['image'];

        if ($image->getError() === UPLOAD_ERR_OK) {
            $filename = uniqid() . '_' . $image->getClientFilename();
            $image->moveTo(__DIR__ . '/../public/uploads/' . $filename);
            $imageName = $filename;
        }
    }

    if (!$full_name || !$username || !$email || !$password || !$role) {
        $response->getBody()->write(json_encode(['success' => false, 'message' => 'Sila isi semua ruangan.']));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withStatus(400);
    }

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    try {
        $stmt = $pdo->prepare("INSERT INTO users (full_name, username, email, password, role, image) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$full_name, $username, $email, $hashedPassword, $role, $imageName]);

        $response->getBody()->write(json_encode(['success' => true, 'message' => 'Pendaftaran berjaya!']));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withStatus(201);
    } catch (PDOException $e) {
        $response->getBody()->write(json_encode(['success' => false, 'message' => 'Pendaftaran gagal.', 'details' => $e->getMessage()]));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withStatus(500);
    }
});


// Lecturer login 
$app->post('/login', function ($request, $response) {
    $pdo = getPDO();
    $data = $request->getParsedBody();

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$data['username']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($data['password'], $user['password'])) {
    
        $userData = [
            'full_name' => $user['full_name'],
            'username' => $user['username'],
            'role' => $user['role'],
            'image' => $user['image'] ?? 'default.jpg'
        ];

        $response->getBody()->write(json_encode([
            'success' => true,
            'user' => $userData
        ]));
        return $response->withHeader('Content-Type', 'application/json')
                        ->withHeader('Access-Control-Allow-Origin', '*');
    } else {
        $response->getBody()->write(json_encode([
            'success' => false,
            'message' => 'ID atau kata laluan salah.'
        ]));
        return $response->withHeader('Content-Type', 'application/json')
                        ->withHeader('Access-Control-Allow-Origin', '*');
    }
});


// untuk dipaparkan di utk paparkan di http://localhost:8085/users (lecturer,student,advisor)
$app->get('/users', function ($request, $response) {
    try {
        $pdo = getPDO();
        $stmt = $pdo->query("SELECT * FROM users");
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json')
                        ->withHeader('Access-Control-Allow-Origin', '*')
                        ->withStatus(200);
    } catch (PDOException $e) {
        $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
        return $response->withHeader('Content-Type', 'application/json')
                        ->withHeader('Access-Control-Allow-Origin', '*')
                        ->withStatus(500);
    }
});


// feature 3) assessment management list
// add componenet assessment

$app->post('/assessment', function ($request, $response) {
    $pdo = getPDO();
    $data = json_decode($request->getBody()->getContents(), true);

    $CourseCode = $data['CourseCode'] ?? '';
    $AssessmentName = $data['AssessmentName'] ?? '';
    $Percentage = $data['Percentage'] ?? '';

    $ComponentType = isset($data['ComponentType']) && trim($data['ComponentType']) !== '' 
        ? $data['ComponentType'] 
        : 'CA';

    // (Optional) log untuk debug
    file_put_contents("debug_post_data.log", json_encode($data, JSON_PRETTY_PRINT));

    if ($CourseCode === '' || $AssessmentName === '' || $Percentage === '') {
        $response->getBody()->write(json_encode([
            'success' => false,
            'message' => 'Sila isi semua ruangan',
            'debug' => $data
        ]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO assessment (CourseCode, AssessmentName, Percentage, ComponentType) VALUES (?, ?, ?, ?)");
        $stmt->execute([$CourseCode, $AssessmentName, $Percentage, $ComponentType]);

        $response->getBody()->write(json_encode([
            'success' => true,
            'message' => 'Assessment success'
        ]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
    } catch (PDOException $e) {
        $response->getBody()->write(json_encode([
            'success' => false,
            'message' => 'assessment failed',
            'details' => $e->getMessage()
        ]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }
});




//DEKAT LOCALHOST (http://localhost:8085/assessment/SECJ2154) SERTA BUAT PAPARAN DI FRONEND TABLE..
$app->get('/assessment/{CourseCode}', function ($request, $response, $args) {
    $pdo = getPDO();
    $CourseCode = $args['CourseCode'];

    try {
        $stmt = $pdo->prepare("SELECT * FROM assessment WHERE CourseCode = ?");
        $stmt->execute([$CourseCode]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    } catch (PDOException $e) {
        $response->getBody()->write(json_encode([
            'success' => false,
            'message' => 'Gagal ambil assessment',
            'details' => $e->getMessage()
        ]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }
});


//http://localhost:8085/assessment
$app->get('/assessment', function ($request, $response) {
    $pdo = getPDO();

    try {
        $stmt = $pdo->query("SELECT * FROM assessment");
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    } catch (PDOException $e) {
        $response->getBody()->write(json_encode([
            'success' => false,
            'message' => 'Gagal ambil assessment',
            'details' => $e->getMessage()
        ]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }
});

//------------------------------------belum settle-------------------------------------------
//edit component assessment
$app->put('/assessment/{assessmentID}', function ($request, $response, $args) {
    $pdo = getPDO();
    $assessmentID = $args['assessmentID'];
    $data = json_decode($request->getBody()->getContents(), true);

    $AssessmentName = $data['AssessmentName'] ?? '';
    $Percentage = $data['Percentage'] ?? '';
    $ComponentType = $data['ComponentType'] ?? 'CA';


    if (!$AssessmentName || !$Percentage) {
        $response->getBody()->write(json_encode([
            'success' => false,
            'message' => 'Sila isi semua ruangan'
        ]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }

    try {
        $stmt = $pdo->prepare("UPDATE assessment SET AssessmentName = ?, Percentage = ?, ComponentType = ? WHERE assessmentID = ?");
        $stmt->execute([$AssessmentName, $Percentage, $ComponentType, $assessmentID]);

        $response->getBody()->write(json_encode([
            'success' => true,
            'message' => 'Assessment berjaya dikemaskini'
        ]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    } catch (PDOException $e) {
        $response->getBody()->write(json_encode([
            'success' => false,
            'message' => 'Gagal kemaskini assessment',
            'details' => $e->getMessage()
        ]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }
});

//delete component assessment
$app->delete('/assessment/{assessmentID}', function ($request, $response, $args) {
    $pdo = getPDO();
    $assessmentID = $args['assessmentID'];

    try {
        $stmt = $pdo->prepare("DELETE FROM assessment WHERE assessmentID = ?");
        $stmt->execute([$assessmentID]);

        $response->getBody()->write(json_encode([
            'success' => true,
            'message' => 'Assessment berjaya dipadam'
        ]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    } catch (PDOException $e) {
        $response->getBody()->write(json_encode([
            'success' => false,
            'message' => 'Gagal padam assessment',
            'details' => $e->getMessage()
        ]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }
});

//--------------------------------------blm settle-----------------------------------------------

// functin enter continuous assessment (input mark for student)

$app->get('/mark/{CourseCode}', function ($request, $response, $args) {
    $pdo = getPDO();
    $CourseCode = $args['CourseCode'];

    try {
        $stmt = $pdo->prepare("
            SELECT s.studentID, a.assessmentID AS assessmentID, IFNULL(m.Mark, 0) AS mark
            FROM student_list s
            JOIN student_course sc ON s.studentID = sc.studentID
            JOIN assessment a ON sc.CourseCode = a.CourseCode
            LEFT JOIN mark m ON s.studentID = m.StudentID AND a.assessmentID = m.assessmentID
            WHERE sc.CourseCode = ?
        ");
        $stmt->execute([$CourseCode]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);

    } catch (PDOException $e) {
        $response->getBody()->write(json_encode([
            'success' => false,
            'message' => 'Gagal ambil markah',
            'details' => $e->getMessage()
        ]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }
});



$app->get('/students/{CourseCode}', function ($request, $response, $args) {
    $pdo = getPDO();
    $CourseCode = $args['CourseCode'];

    $stmt = $pdo->prepare("
        SELECT s.studentID, s.studentName 
        FROM student_list s
        JOIN student_course sc ON s.studentID = sc.studentID
        WHERE sc.CourseCode = ?
    ");
    $stmt->execute([$CourseCode]);
    $data = $stmt->fetchAll();

    $response->getBody()->write(json_encode($data));
    return $response->withHeader('Content-Type', 'application/json');
});


$app->post('/assessment/mark', function ($request, $response) {
    $pdo = getPDO();
    $data = json_decode($request->getBody()->getContents(), true);

    $studentID = $data['studentID'] ?? '';
    $assessmentID = $data['assessmentID'] ?? '';
    $mark = $data['mark'] ?? '';

    if (!$studentID || !$assessmentID || $mark === '') {
        return $response->withHeader('Content-Type', 'application/json')
                        ->withStatus(400)
                        ->write(json_encode(['success' => false, 'message' => 'All fields required']));
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO mark (assessmentID, studentID, mark)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE mark = VALUES(mark)
        ");
        $stmt->execute([$assessmentID, $studentID, $mark]);

        $response->getBody()->write(json_encode(['success' => true]));
        return $response->withHeader('Content-Type', 'application/json');
    } catch (PDOException $e) {
        $response->getBody()->write(json_encode([
            'success' => false,
            'message' => 'Gagal simpan markah',
            'details' => $e->getMessage()
        ]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }
});

$app->get('/mark', function ($request, $response) {
    $pdo = getPDO();

    try {
        $stmt = $pdo->query("SELECT * FROM mark");
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);

    } catch (PDOException $e) {
        $response->getBody()->write(json_encode([
            'success' => false,
            'message' => 'Gagal ambil markah',
            'details' => $e->getMessage()
        ]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }
});


// untuk dapatkan gred student
$app->get('/result/{CourseCode}', function ($request, $response, $args) {
    $pdo = getPDO();
    $CourseCode = $args['CourseCode'];

    try {
        $stmt = $pdo->prepare("
            SELECT 
            s.studentID, 
            s.StudentName AS studentName, -- ✅ alias lowercase
            a.assessmentID, 
            a.AssessmentName AS assessmentName,
            a.Percentage AS percentage,
            m.mark
            FROM student_list s
            JOIN student_course sc ON s.studentID = sc.studentID
            JOIN assessment a ON a.CourseCode = sc.CourseCode
            LEFT JOIN mark m ON m.studentID = s.studentID AND m.assessmentID = a.assessmentID
            WHERE sc.CourseCode = ?
            ORDER BY s.studentID, a.assessmentID
        ");
        $stmt->execute([$CourseCode]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);

    } catch (PDOException $e) {
        $response->getBody()->write(json_encode([
            'success' => false,
            'message' => 'Gagal ambil result pelajar',
            'details' => $e->getMessage()
        ]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }
});


// pai chart dashboardoverview page
$app->get('/dashboard/totalStudentsByCourse', function ($request, $response) {
    $pdo = getPDO();

    $stmt = $pdo->query("
        SELECT c.CourseName, COUNT(sc.StudentID) AS totalStudents
        FROM course_list c
        LEFT JOIN student_course sc ON c.CourseCode = sc.CourseCode
        GROUP BY c.CourseCode
    ");

    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $response->getBody()->write(json_encode($data));
    return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
});

$app->get('/dashboard/averageMarkByCourse', function ($request, $response) {
    $pdo = getPDO();

    $stmt = $pdo->query("
        SELECT 
            c.CourseName, 
            IFNULL(AVG(am.mark), 0) AS averageMark
        FROM 
            course_list c
        LEFT JOIN 
            assessment a ON a.CourseCode = c.CourseCode
        LEFT JOIN 
            mark am ON am.assessmentID = a.assessmentID
        GROUP BY 
            c.CourseCode
    ");
    
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $response->getBody()->write(json_encode($data));
    return $response->withHeader('Content-Type', 'application/json');
});

$app->get('/lecturer/appeals/pending-count', function ($request, $response) {
    $pdo = getPDO();
    $stmt = $pdo->query("SELECT COUNT(*) AS total FROM grade_appeal WHERE status = 'Pending'");
    $count = $stmt->fetch(PDO::FETCH_ASSOC);
    $response->getBody()->write(json_encode($count));
    return $response->withHeader('Content-Type', 'application/json');
});
















/*
    Ini adalah untuk role student--------------------------------------------------------------------------------
*/

// student login selepas advisor createkan account


$app->post('/student/login', function (Request $request, Response $response) {
    $pdo = getPDO();
    $data = $request->getParsedBody();

    $username = $data['username'] ?? '';
    $password = $data['password'] ?? '';

    // Cari pelajar berdasarkan username
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND role = 'student'");
    $stmt->execute([$username]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($student && password_verify($password, $student['password'])) {
        // Login berjaya
        $response->getBody()->write(json_encode([
            'success' => true,
            'studentID' => $student['username'],
            'studentName' => $student['full_name']
        ]));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withStatus(200);
    } else {
        // Login gagal
        $response->getBody()->write(json_encode([
            'success' => false,
            'message' => 'Username atau password salah.'
        ]));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withStatus(401);
    }
});

// 1)grade appeal



//paparkan senarai kursus yang student enroll
$app->get('/student/courses/{studentID}', function ($request, $response, $args) {
    $studentID = $args['studentID'];
    $pdo = getPDO();

    $stmt = $pdo->prepare("
        SELECT c.CourseCode, c.CourseName
        FROM student_course sc
        JOIN course_list c ON sc.CourseCode = c.CourseCode
        WHERE sc.studentID = :studentID
    ");
    $stmt->execute(['studentID' => $studentID]);
    
    // FIXED: Gunakan FETCH_ASSOC untuk dapatkan hasil dalam format array associatif
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $response->getBody()->write(json_encode($courses));
    return $response->withHeader('Content-Type', 'application/json');
});




//paparkan markah pelajar berserta appeal status
$app->get('/student/assessment-summary/{studentID}', function ($request, $response, $args) {
    $pdo = getPDO();
    $studentID = $args['studentID'];

    $stmt = $pdo->prepare("
        SELECT 
            a.assessmentID,
            c.CourseCode,
            c.CourseName,
            a.AssessmentName,
            a.Percentage AS MaxScore,
            m.mark,
            COALESCE(ga.status, 'Not Requested') AS status
        FROM mark m
        JOIN assessment a ON m.assessmentID = a.assessmentID
        JOIN course_list c ON a.CourseCode = c.CourseCode
        LEFT JOIN grade_appeal ga ON ga.studentID = m.studentID AND ga.assessmentID = m.assessmentID
        WHERE m.studentID = ?
    ");
    $stmt->execute([$studentID]);
    $marks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $response->getBody()->write(json_encode($marks));
    return $response->withHeader('Content-Type', 'application/json');
});

//hantar permohonan rayuan pelajar
$app->post('/student/grade-appeal', function ($request, $response) {
    $pdo = getPDO();
    $data = json_decode($request->getBody()->getContents(), true);

    $studentID = $data['studentID'];
    $assessmentID = $data['assessmentID'];
    $reason = $data['reason'];
    $appealedMark = $data['appealedMark'];

    // Semak jika pelajar sudah buat appeal untuk assessment ini
    $check = $pdo->prepare("SELECT * FROM grade_appeal WHERE studentID = ? AND assessmentID = ?");
    $check->execute([$studentID, $assessmentID]);

    if ($check->rowCount() > 0) {
        $res = ['message' => 'You have already submitted an appeal for this assessment.'];
    } else {
        $insert = $pdo->prepare("
            INSERT INTO grade_appeal (studentID, assessmentID, reason, appealedMark)
            VALUES (?, ?, ?, ?)
        ");
        $insert->execute([$studentID, $assessmentID, $reason, $appealedMark]);
        $res = ['message' => 'Your appeal has been submitted successfully.'];
    }

    $response->getBody()->write(json_encode($res));
    return $response->withHeader('Content-Type', 'application/json');
});

// function 2


//function comparison
$app->get('/student/assessment-comparison/{studentID}', function ($request, $response, $args) {
    $pdo = getPDO();
    $studentID = $args['studentID'];

    $sql = "
        SELECT 
            CourseCode,
            CourseName,
            AssessmentName,
            yourMark,
            classAvg,
            topMark
        FROM view_student_comparison
        WHERE studentID = :studentID
        ORDER BY CourseCode, AssessmentName
    ";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['studentID' => $studentID]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

       $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 
        'application/json')->withStatus(200);
    } catch (PDOException $e) {
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(500)
            ->write(json_encode([
                'error' => 'Database error',
                'details' => $e->getMessage()
            ]));
    }
});

// Get student ranking based on studentID
$app->get('/student/ranking/{studentID}', function ($request, $response, $args) {
    $pdo = getPDO();
    $studentID = $args['studentID'];

    $stmt = $pdo->prepare("
        SELECT s.studentID, s.totalMarks,
               (SELECT COUNT(*) + 1 
                FROM students s2 
                WHERE s2.totalMarks > s.totalMarks) AS rank,
               (SELECT COUNT(*) FROM students) AS totalStudents
        FROM students s
        WHERE s.studentID = ?
    ");
    $stmt->execute([$studentID]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result) {
        $result['topPercent'] = round(($result['rank'] / $result['totalStudents']) * 100, 2);
        $response->getBody()->write(json_encode($result));
    } else {
        $response->getBody()->write(json_encode(["error" => "Student not found"]));
        return $response->withStatus(404);
    }

    return $response->withHeader('Content-Type', 'application/json');
});



//dashboard page

//Fetch Summary (Total Mark & Percentage)
$app->get('/student/summary/{studentID}', function ($request, $response, $args) {
    $pdo = getPDO();
    $studentID = $args['studentID'];

    $stmt = $pdo->prepare("
        SELECT 
            SUM(m.mark) AS totalMark,
            SUM(a.percentage) AS maxTotal
        FROM mark m
        JOIN assessment a ON m.assessmentID = a.assessmentID
        WHERE m.studentID = ?
    ");
    $stmt->execute([$studentID]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result) {
        $percentage = ($result['maxTotal'] > 0) ? round(($result['totalMark'] / $result['maxTotal']) * 100, 2) : 0;
        $result['percentage'] = $percentage;
        $response->getBody()->write(json_encode($result));
    } else {
        $response->getBody()->write(json_encode(["error" => "No marks found"]));
        return $response->withStatus(404);
    }

    return $response->withHeader('Content-Type', 'application/json');
});


//Fetch Performance Comparison (You vs Avg vs Top)
$app->get('/student/performance-comparison/{studentID}', function ($request, $response, $args) {
    $pdo = getPDO();
    $studentID = $args['studentID'];

    // Get student's total mark
    $stmt1 = $pdo->prepare("
        SELECT SUM(m.mark) AS total
        FROM mark m
        JOIN assessment a ON m.assessmentID = a.assessmentID
        WHERE m.studentID = ?
    ");
    $stmt1->execute([$studentID]);
    $yourScore = $stmt1->fetchColumn();

    // Average mark of all students
    $stmt2 = $pdo->query("
        SELECT AVG(sub.totalMark) AS average
        FROM (
            SELECT studentID, SUM(mark) AS totalMark
            FROM mark
            GROUP BY studentID
        ) sub
    ");
    $averageScore = $stmt2->fetchColumn();

    // Top mark among all students
    $stmt3 = $pdo->query("
        SELECT MAX(sub.totalMark) AS top
        FROM (
            SELECT studentID, SUM(mark) AS totalMark
            FROM mark
            GROUP BY studentID
        ) sub
    ");
    $topScore = $stmt3->fetchColumn();

    $result = [
        "you" => (float)$yourScore,
        "average" => round($averageScore, 2),
        "top" => (float)$topScore
    ];

    $response->getBody()->write(json_encode($result));
    return $response->withHeader('Content-Type', 'application/json');
});

$app->get('/analytics/{studentID}', function ($request, $response, $args) {
    $studentID = $args['studentID'];
    $pdo = getPDO(); // Fungsi anda untuk sambungan DB

    $sql = "
        SELECT 
            a.CourseCode,
            c.CourseName,
            a.AssessmentName,
            m.mark AS yourMark,
            a.percentage AS MaxScore,
            -- purata kelas
            (SELECT AVG(m2.mark) FROM mark m2 WHERE m2.assessmentID = m.assessmentID) AS classAvg,
            -- markah tertinggi
            (SELECT MAX(m3.mark) FROM mark m3 WHERE m3.assessmentID = m.assessmentID) AS topMark
        FROM mark m
        JOIN assessment a ON m.assessmentID = a.assessmentID
        JOIN course_list c ON a.CourseCode = c.CourseCode
        WHERE m.studentID = :studentID
    ";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['studentID' => $studentID]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json');
    } catch (PDOException $e) {
        $error = ['error' => $e->getMessage()];
        $response->getBody()->write(json_encode($error));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
});













/*
    Ini adalah untuk role advisor---------------------------------------------------------------------------------
*/

// advisor signup (role advisor)

$app->post('/advisor/signup', function ($request, $response) {
    $pdo = getPDO();
    $data = $request->getParsedBody();

    $username = $data['username'] ?? '';
    $full_name = $data['full_name'] ?? '';
    $email = $data['email'] ?? '';
    $password = $data['password'] ?? '';
    $role = 'advisor';
    $image = 'default.jpg';

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    try {
        $stmt = $pdo->prepare("
            INSERT INTO users (username, full_name, email, password, role, image, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$username, $full_name, $email, $hashedPassword, $role, $image]);

        $response->getBody()->write(json_encode([
            'success' => true,
            'message' => 'Advisor berjaya didaftarkan.'
        ]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(201);

    } catch (PDOException $e) {
        $response->getBody()->write(json_encode([
            'success' => false,
            'message' => 'Pendaftaran gagal',
            'error' => $e->getMessage()
        ]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }
});


// Advisor login as (role advisor)

$app->post('/advisor/login', function ($request, $response) {
    $pdo = getPDO();
    $data = $request->getParsedBody();

    $username = $data['username'] ?? '';
    $password = $data['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        $userData = [
            'user_id' => $user['user_id'],
            'full_name' => $user['full_name'],
            'username' => $user['username'],
            'role' => $user['role'],
            'image' => $user['image'] ?? 'default.jpg'
        ];

        $response->getBody()->write(json_encode([
            'success' => true,
            'user' => $userData
        ]));
        return $response->withHeader('Content-Type', 'application/json');

    } else {
        $response->getBody()->write(json_encode([
            'success' => false,
            'message' => 'Nama pengguna atau kata laluan salah.'
        ]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
    }
});


// function 1 - addvisor create account for student

$app->post('/advisor/add-student', function ($request, $response) {
    $pdo = getPDO();

    $parsedBody = $request->getParsedBody();
    $uploadedFiles = $request->getUploadedFiles();

    $full_name = $parsedBody['full_name'] ?? '';
    $username = $parsedBody['username'] ?? '';
    $email = $parsedBody['email'] ?? '';
    $password = $parsedBody['password'] ?? '';
    $role = 'student';
    $created_at = date('Y-m-d H:i:s');
    $imageName = 'default.jpg';

    // ✅ Check if required fields are filled
    if (!$username || !$full_name || !$email || !$password) {
        $payload = json_encode(['success' => false, 'message' => 'Maklumat tidak lengkap']);
        $response->getBody()->write($payload);
        return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }

    // ✅ Handle image upload (optional)
    if (isset($uploadedFiles['image'])) {
        $image = $uploadedFiles['image'];

        if ($image->getError() === UPLOAD_ERR_OK) {
            $extension = pathinfo($image->getClientFilename(), PATHINFO_EXTENSION);
            $imageName = uniqid('img_') . '.' . $extension;

            $uploadDirectory = __DIR__ . '/../uploads/';
            if (!is_dir($uploadDirectory)) {
                mkdir($uploadDirectory, 0777, true);
            }

            $image->moveTo($uploadDirectory . $imageName);
        }
    }

    try {
        // ✅ Check if username already exists
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->rowCount() > 0) {
            $payload = json_encode(['success' => false, 'message' => 'Username telah wujud']);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json')->withStatus(409);
        }

        // ✅ Insert student into database
        $stmt = $pdo->prepare("
            INSERT INTO users (username, full_name, email, password, role, image, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $stmt->execute([
            $username, $full_name, $email, $hashedPassword, $role, $imageName, $created_at
        ]);

        $payload = json_encode(['success' => true, 'message' => 'Akaun pelajar berjaya didaftarkan.']);
        $response->getBody()->write($payload);
        return $response->withHeader('Content-Type', 'application/json')->withStatus(201);

    } catch (PDOException $e) {
        $payload = json_encode([
            'success' => false,
            'message' => 'Ralat pelayan.',
            'error' => $e->getMessage()
        ]);
        $response->getBody()->write($payload);
        return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }
});

// function 2  view advisee
$app->get('/advisees/ranking', function ($request, $response) {
    try {
        $pdo = getPDO();

        // Guna VIEW yang dah siap
        $sql = "SELECT * FROM view_advisees_ranking";

        $stmt = $pdo->query($sql);
        $advisees = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Hantar data
        $response->getBody()->write(json_encode($advisees));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    } catch (PDOException $e) {
        $error = [
            'error' => 'Database error',
            'details' => $e->getMessage()
        ];
        $response->getBody()->write(json_encode($error));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }
});


//function 3

//dapatkan dropdown
// GET all courses
$app->get('/api/courses', function ($request, $response) {
    try {
        $pdo = getPDO();
        $stmt = $pdo->query("SELECT CourseCode, CourseName FROM course_list");
        $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $response->getBody()->write(json_encode($courses));
        return $response->withHeader('Content-Type', 'application/json');
    } catch (PDOException $e) {
        return $response->withStatus(500)->write(json_encode(['error' => $e->getMessage()]));
    }
});


// GET dynamic assessment marks by course
$app->get('/api/marks/course-assessments/{courseCode}', function ($request, $response, $args) {
    $courseCode = $args['courseCode'];
    try {
        $pdo = getPDO();
        $sql = "
            SELECT 
                m.studentID,
                u.full_name AS studentName,
                a.CourseCode,
                c.CourseName,
                a.AssessmentName,
                m.mark AS marks
            FROM mark m
            JOIN assessment a ON m.assessmentID = a.assessmentID
            JOIN course_list c ON a.CourseCode = c.CourseCode
            JOIN users u ON m.studentID = u.username
            WHERE a.CourseCode = :courseCode
            ORDER BY m.studentID, a.AssessmentName
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['courseCode' => $courseCode]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json');
    } catch (PDOException $e) {
        $error = ['error' => $e->getMessage()];
        $response->getBody()->write(json_encode($error));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
});






// notes
$app->post('/api/notes', function ($request, $response) {
    $data = $request->getParsedBody();

    $studentID = $data['student_id'] ?? null;
    $note = $data['note'] ?? null;
    $meeting_date = $data['meeting_date'] ?? null;

    if (!$studentID || !$note) {
        return $response->withStatus(400)->withJson(['success' => false, 'message' => 'Missing student ID or note']);
    }

    $pdo = getPDO();

    $stmt = $pdo->prepare("INSERT INTO advisor_note (studentID, note, meeting_date, created_at)
                           VALUES (?, ?, ?, NOW())");
    $stmt->execute([$studentID, $note, $meeting_date]);

    return $response->withJson(['success' => true, 'message' => 'Note added successfully']);
});


$app->get('/api/notes/{studentID}', function ($request, $response, $args) {
    $studentID = $args['studentID'];

    $pdo = getPDO();
    $stmt = $pdo->prepare("SELECT * FROM advisor_note WHERE studentID = ? ORDER BY created_at DESC");
    $stmt->execute([$studentID]);
    $notes = $stmt->fetchAll();

    return $response->withJson($notes);
});


//export reports
$app->get('/api/marks/{studentID}/{courseCode}', function ($request, $response, $args) {
    $studentID = $args['studentID'];
    $courseCode = $args['courseCode'];

    $pdo = getPDO();

    try {
        $stmt = $pdo->prepare("
            SELECT 
                m.studentID AS student_id,
                u.full_name AS student_name,
                c.CourseCode AS course_code,
                c.CourseName AS course_name,
                MAX(CASE WHEN a.AssessmentName = 'Assignment' THEN m.mark ELSE 0 END) AS assignment,
                MAX(CASE WHEN a.AssessmentName = 'Quiz' THEN m.mark ELSE 0 END) AS quiz,
                MAX(CASE WHEN a.AssessmentName = 'Project' THEN m.mark ELSE 0 END) AS project,
                MAX(CASE WHEN a.AssessmentName = 'Midterm' THEN m.mark ELSE 0 END) AS midterm,
                MAX(CASE WHEN a.AssessmentName = 'Final Exam' THEN m.mark ELSE 0 END) AS final_exam
            FROM mark m
            JOIN assessment a ON m.assessmentID = a.assessmentID
            JOIN course_list c ON a.CourseCode = c.CourseCode
            JOIN users u ON m.studentID = u.username
            WHERE m.studentID = :studentID AND a.CourseCode = :courseCode
            GROUP BY m.studentID, u.full_name, c.CourseCode, c.CourseName
        ");
        $stmt->execute([
            'studentID' => $studentID,
            'courseCode' => $courseCode
        ]);

        $marks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $response->getBody()->write(json_encode($marks));
        return $response->withHeader('Content-Type', 'application/json');

    } catch (PDOException $e) {
        $error = ['error' => $e->getMessage()];
        $response->getBody()->write(json_encode($error));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
});


// $app->get('/api/marks', function ($request, $response) {
//     $pdo = getPDO();

//     $sql = "
//         SELECT 
//             m.studentID,
//             s.studentName,
//             a.CourseCode,
//             c.CourseName,
//             a.AssessmentName,
//             m.mark
//         FROM mark m
//         JOIN assessments a ON a.assessmentID = m.assessmentID
//         JOIN students s ON s.studentID = m.studentID
//         JOIN courses c ON c.CourseCode = a.CourseCode
//         ORDER BY m.studentID, a.CourseCode, a.AssessmentName
//     ";

//     $stmt = $pdo->query($sql);
//     $marks = $stmt->fetchAll(PDO::FETCH_ASSOC);

//     return $response->withHeader('Content-Type', 'application/json')
//                     ->withStatus(200)
//                     ->write(json_encode($marks));
// });





//CORS Middleware (Manual)
$app->add(function ($request, $handler) {
    $response = $handler->handle($request);
    return $response
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
});

// Handle OPTIONS preflight request
$app->options('/{routes:.+}', function ($request, $response) {
    return $response;
});


$app->run();



