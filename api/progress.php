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

$progress = new Progress($conn);

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        try {
            $action = $_GET['action'] ?? 'summary';

            switch ($action) {
                case 'summary':
                    // Genel ilerleme özeti
                    $user_progress = $progress->getUserProgress($_SESSION['user_id']);
                    $stats = $progress->getUserStats($_SESSION['user_id']);

                    json_response([
                        'success' => true,
                        'data' => [
                            'progress' => $user_progress,
                            'stats' => $stats
                        ]
                    ]);
                    break;

                case 'course_progress':
                    // Kurs ilerlemesi
                    if (!isset($_GET['course_id'])) {
                        throw new Exception('Kurs ID gereklidir.');
                    }

                    $course_progress = $progress->getCourseProgress(
                        $_SESSION['user_id'],
                        $_GET['course_id']
                    );

                    $completion = $progress->checkCourseCompletion(
                        $_SESSION['user_id'],
                        $_GET['course_id']
                    );

                    json_response([
                        'success' => true,
                        'data' => [
                            'modules' => $course_progress,
                            'completion' => $completion
                        ]
                    ]);
                    break;

                case 'report':
                    // Detaylı ilerleme raporu
                    if (!isset($_GET['course_id'])) {
                        throw new Exception('Kurs ID gereklidir.');
                    }

                    $report = $progress->getProgressReport(
                        $_SESSION['user_id'],
                        $_GET['course_id']
                    );

                    json_response([
                        'success' => true,
                        'data' => $report
                    ]);
                    break;

                case 'recent_activities':
                    // Son aktiviteler
                    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
                    $activities = $progress->getRecentActivities(
                        $_SESSION['user_id'],
                        $limit
                    );

                    json_response([
                        'success' => true,
                        'data' => $activities
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
            $input = json_decode(file_get_contents('php://input'), true);
            $action = $input['action'] ?? 'update';

            switch ($action) {
                case 'update':
                    // İlerleme güncelle
                    if (!isset($input['module_id']) || !isset($input['status'])) {
                        throw new Exception('Modül ID ve durum gereklidir.');
                    }

                    $progress->user_id = $_SESSION['user_id'];
                    $progress->course_id = $input['course_id'];
                    $progress->module_id = $input['module_id'];
                    $progress->status = $input['status'];
                    $progress->progress_percentage = $input['progress_percentage'] ?? 
                        ($input['status'] === 'completed' ? 100 : 0);

                    if ($progress->saveProgress()) {
                        // Kurs tamamlanma durumunu kontrol et
                        $completion = $progress->checkCourseCompletion(
                            $_SESSION['user_id'],
                            $input['course_id']
                        );

                        // Kurs tamamlandıysa sertifika oluştur
                        if ($completion['completed']) {
                            $certificate = new Certificate($conn);
                            $certificate->user_id = $_SESSION['user_id'];
                            $certificate->course_id = $input['course_id'];
                            $certificate->create();

                            // Bildirim gönder
                            $notification = new Notification($conn);
                            $notification->createNotification(
                                $_SESSION['user_id'],
                                'course_completed',
                                'Tebrikler! Kursu başarıyla tamamladınız.',
                                '/user/certificates.php'
                            );
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

                case 'reset':
                    // İlerlemeyi sıfırla (Admin veya eğitmen için)
                    if (!in_array($_SESSION['role'], ['admin', 'instructor'])) {
                        throw new Exception('Bu işlem için yetkiniz yok.');
                    }

                    if (!isset($input['user_id'])) {
                        throw new Exception('Kullanıcı ID gereklidir.');
                    }

                    if ($progress->resetProgress($input['user_id'], $input['course_id'] ?? null)) {
                        json_response([
                            'success' => true,
                            'message' => 'İlerleme başarıyla sıfırlandı.'
                        ]);
                    } else {
                        throw new Exception('İlerleme sıfırlanırken bir hata oluştu.');
                    }
                    break;

                case 'bulk_update':
                    // Toplu ilerleme güncelleme (Admin veya eğitmen için)
                    if (!in_array($_SESSION['role'], ['admin', 'instructor'])) {
                        throw new Exception('Bu işlem için yetkiniz yok.');
                    }

                    if (!isset($input['updates']) || !is_array($input['updates'])) {
                        throw new Exception('Güncellenecek veriler gereklidir.');
                    }

                    $success = true;
                    $conn->beginTransaction();

                    try {
                        foreach ($input['updates'] as $update) {
                            $progress->user_id = $update['user_id'];
                            $progress->course_id = $update['course_id'];
                            $progress->module_id = $update['module_id'];
                            $progress->status = $update['status'];
                            $progress->progress_percentage = $update['progress_percentage'] ?? 
                                ($update['status'] === 'completed' ? 100 : 0);

                            if (!$progress->saveProgress()) {
                                $success = false;
                                break;
                            }
                        }

                        if ($success) {
                            $conn->commit();
                            json_response([
                                'success' => true,
                                'message' => 'Toplu güncelleme başarıyla tamamlandı.'
                            ]);
                        } else {
                            throw new Exception('Bazı güncellemeler başarısız oldu.');
                        }
                    } catch (Exception $e) {
                        $conn->rollBack();
                        throw $e;
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