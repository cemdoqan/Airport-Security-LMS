<?php
require_once '../includes/config.php';

header('Content-Type: application/json');

// Oturum kontrolü
if (!isset($_SESSION['user_id'])) {
    json_response([
        'success' => false,
        'error' => 'Oturum açmanız gerekiyor.'
    ], 401);
}

$course = new Course($conn);
$progress = new Progress($conn);

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        try {
            $action = $_GET['action'] ?? 'list';

            switch ($action) {
                case 'list':
                    // Kurs listesi
                    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
                    $start = ($page - 1) * $limit;

                    $filters = [
                        'category' => $_GET['category'] ?? null,
                        'status' => $_GET['status'] ?? null,
                        'search' => $_GET['search'] ?? null
                    ];

                    $courses = $course->getAll($start, $limit, $filters);
                    $total = $course->getTotal($filters);

                    // Her kurs için ilerleme durumunu ekle
                    foreach ($courses as &$item) {
                        $courseProgress = $progress->getCourseProgress($_SESSION['user_id'], $item['id']);
                        $item['progress'] = [
                            'total_modules' => $item['module_count'],
                            'completed_modules' => count(array_filter($courseProgress, function($module) {
                                return $module['status'] === 'completed';
                            })),
                            'last_accessed' => $courseProgress ? max(array_column($courseProgress, 'last_accessed')) : null
                        ];
                        $item['progress']['percentage'] = $item['progress']['total_modules'] > 0 ? 
                            ($item['progress']['completed_modules'] / $item['progress']['total_modules'] * 100) : 0;
                    }

                    json_response([
                        'success' => true,
                        'data' => $courses,
                        'pagination' => [
                            'total' => $total,
                            'page' => $page,
                            'limit' => $limit,
                            'total_pages' => ceil($total / $limit)
                        ]
                    ]);
                    break;

                case 'detail':
                    // Kurs detayı
                    if (!isset($_GET['id'])) {
                        throw new Exception('Kurs ID gereklidir.');
                    }

                    $course_id = (int)$_GET['id'];
                    $course_data = $course->getById($course_id);

                    if (!$course_data) {
                        throw new Exception('Kurs bulunamadı.');
                    }

                    // Modülleri ekle
                    $course_data['modules'] = $course->getModules();

                    // İlerleme durumunu ekle
                    $courseProgress = $progress->getCourseProgress($_SESSION['user_id'], $course_id);
                    $course_data['progress'] = [
                        'modules' => $courseProgress,
                        'total_modules' => count($course_data['modules']),
                        'completed_modules' => count(array_filter($courseProgress, function($module) {
                            return $module['status'] === 'completed';
                        }))
                    ];
                    $course_data['progress']['percentage'] = 
                        $course_data['progress']['total_modules'] > 0 ? 
                        ($course_data['progress']['completed_modules'] / $course_data['progress']['total_modules'] * 100) : 0;

                    json_response([
                        'success' => true,
                        'data' => $course_data
                    ]);
                    break;

                case 'progress':
                    // Kullanıcının kurs ilerlemesi
                    $user_progress = $progress->getUserProgress($_SESSION['user_id']);
                    $stats = $progress->getUserStats($_SESSION['user_id']);

                    json_response([
                        'success' => true,
                        'data' => [
                            'courses' => $user_progress,
                            'stats' => $stats
                        ]
                    ]);
                    break;

                default:
                    throw new Exception('Geçersiz işlem.');
            }
        } catch (Exception $e) {
            json_response([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
        break;

    case 'POST':
        try {
            // Admin ve eğitmen kontrolü
            if (!in_array($_SESSION['role'], ['admin', 'instructor'])) {
                throw new Exception('Bu işlem için yetkiniz yok.');
            }

            $input = json_decode(file_get_contents('php://input'), true);
            $action = $input['action'] ?? 'create';

            switch ($action) {
                case 'create':
                    // Kurs oluştur
                    $course->title = $input['title'];
                    $course->description = $input['description'];
                    $course->content = $input['content'];
                    $course->duration = $input['duration'];
                    $course->category = $input['category'];
                    $course->status = $input['status'];
                    $course->created_by = $_SESSION['user_id'];

                    if ($course->create()) {
                        // Modülleri ekle
                        if (isset($input['modules'])) {
                            foreach ($input['modules'] as $module) {
                                $course->addModule($module);
                            }
                        }

                        json_response([
                            'success' => true,
                            'message' => 'Kurs başarıyla oluşturuldu.',
                            'id' => $course->id
                        ]);
                    } else {
                        throw new Exception('Kurs oluşturulurken bir hata oluştu.');
                    }
                    break;

                case 'update':
                    // Kurs güncelle
                    if (!isset($input['id'])) {
                        throw new Exception('Kurs ID gereklidir.');
                    }

                    $course->id = $input['id'];
                    $course->title = $input['title'];
                    $course->description = $input['description'];
                    $course->content = $input['content'];
                    $course->duration = $input['duration'];
                    $course->category = $input['category'];
                    $course->status = $input['status'];

                    if ($course->update()) {
                        json_response([
                            'success' => true,
                            'message' => 'Kurs başarıyla güncellendi.'
                        ]);
                    } else {
                        throw new Exception('Kurs güncellenirken bir hata oluştu.');
                    }
                    break;

                case 'delete':
                    // Kurs sil
                    if (!isset($input['id'])) {
                        throw new Exception('Kurs ID gereklidir.');
                    }

                    if ($course->delete()) {
                        json_response([
                            'success' => true,
                            'message' => 'Kurs başarıyla silindi.'
                        ]);
                    } else {
                        throw new Exception('Kurs silinirken bir hata oluştu.');
                    }
                    break;

                case 'update_progress':
                    // İlerleme güncelle
                    if (!isset($input['module_id']) || !isset($input['status'])) {
                        throw new Exception('Modül ID ve durum gereklidir.');
                    }

                    $progress->user_id = $_SESSION['user_id'];
                    $progress->course_id = $input['course_id'];
                    $progress->module_id = $input['module_id'];
                    $progress->status = $input['status'];
                    $progress->progress_percentage = $input['progress_percentage'] ?? 100;

                    if ($progress->saveProgress()) {
                        // Kurs tamamlanma durumunu kontrol et
                        $completion = $progress->checkCourseCompletion($_SESSION['user_id'], $input['course_id']);
                        
                        if ($completion['completed']) {
                            // Sertifika oluştur
                            $certificate = new Certificate($conn);
                            $certificate->user_id = $_SESSION['user_id'];
                            $certificate->course_id = $input['course_id'];
                            $certificate->create();
                        }

                        json_response([
                            'success' => true,
                            'message' => 'İlerleme kaydedildi.',
                            'completion' => $completion
                        ]);
                    } else {
                        throw new Exception('İlerleme kaydedilirken bir hata oluştu.');
                    }
                    break;

                default:
                    throw new Exception('Geçersiz işlem.');
            }
        } catch (Exception $e) {
            json_response([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
        break;

    default:
        json_response([
            'success' => false,
            'error' => 'Geçersiz istek methodu.'
        ], 405);
}