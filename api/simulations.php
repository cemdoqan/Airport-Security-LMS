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

$simulation = new Simulation($conn);

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        try {
            $action = $_GET['action'] ?? 'list';

            switch ($action) {
                case 'list':
                    // Simülasyon listesi
                    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
                    $start = ($page - 1) * $limit;

                    $filters = [
                        'difficulty_level' => $_GET['difficulty_level'] ?? null,
                        'status' => $_GET['status'] ?? null,
                        'search' => $_GET['search'] ?? null
                    ];

                    $simulations = $simulation->getAll($start, $limit, $filters);
                    $total = $simulation->getTotal($filters);

                    json_response([
                        'success' => true,
                        'data' => $simulations,
                        'pagination' => [
                            'total' => $total,
                            'page' => $page,
                            'limit' => $limit,
                            'total_pages' => ceil($total / $limit)
                        ]
                    ]);
                    break;

                case 'detail':
                    // Simülasyon detayı
                    if (!isset($_GET['id'])) {
                        throw new Exception('Simülasyon ID gereklidir.');
                    }

                    $simulation_data = $simulation->getById($_GET['id']);
                    if (!$simulation_data) {
                        throw new Exception('Simülasyon bulunamadı.');
                    }

                    json_response([
                        'success' => true,
                        'data' => $simulation_data
                    ]);
                    break;

                case 'random':
                    // Rastgele simülasyon
                    $difficulty_level = $_GET['difficulty_level'] ?? null;
                    $simulation_data = $simulation->getRandom($difficulty_level);

                    if (!$simulation_data) {
                        throw new Exception('Uygun simülasyon bulunamadı.');
                    }

                    json_response([
                        'success' => true,
                        'data' => $simulation_data
                    ]);
                    break;

                case 'user_stats':
                    // Kullanıcı istatistikleri
                    $user_stats = $simulation->getUserStats($_SESSION['user_id']);
                    $recent_attempts = $simulation->getUserAttempts($_SESSION['user_id'], 5);

                    json_response([
                        'success' => true,
                        'data' => [
                            'stats' => $user_stats,
                            'recent_attempts' => $recent_attempts
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
            $input = json_decode(file_get_contents('php://input'), true);
            $action = $input['action'] ?? 'submit_attempt';

            switch ($action) {
                case 'create':
                    // Yetki kontrolü
                    if (!in_array($_SESSION['role'], ['admin', 'instructor'])) {
                        throw new Exception('Bu işlem için yetkiniz yok.');
                    }

                    // Simülasyon oluştur
                    $simulation->title = $input['title'];
                    $simulation->description = $input['description'];
                    $simulation->difficulty_level = $input['difficulty_level'];
                    $simulation->time_limit = $input['time_limit'];
                    $simulation->passing_score = $input['passing_score'];
                    $simulation->status = $input['status'];
                    $simulation->created_by = $_SESSION['user_id'];

                    // X-ray görüntüsünü kaydet
                    if (isset($_FILES['image'])) {
                        $simulation->image_path = upload_file(
                            $_FILES['image'],
                            ['jpg', 'jpeg', 'png'],
                            10485760, // 10MB
                            XRAY_PATH
                        );
                    }

                    // Tehlikeli nesneleri kaydet
                    $simulation->threat_objects = $input['threat_objects'];

                    if ($simulation->create()) {
                        json_response([
                            'success' => true,
                            'message' => 'Simülasyon başarıyla oluşturuldu.',
                            'id' => $simulation->id
                        ]);
                    } else {
                        throw new Exception('Simülasyon oluşturulurken bir hata oluştu.');
                    }
                    break;

                case 'update':
                    // Yetki kontrolü
                    if (!in_array($_SESSION['role'], ['admin', 'instructor'])) {
                        throw new Exception('Bu işlem için yetkiniz yok.');
                    }

                    if (!isset($input['id'])) {
                        throw new Exception('Simülasyon ID gereklidir.');
                    }

                    $simulation->id = $input['id'];
                    $simulation->title = $input['title'];
                    $simulation->description = $input['description'];
                    $simulation->difficulty_level = $input['difficulty_level'];
                    $simulation->time_limit = $input['time_limit'];
                    $simulation->passing_score = $input['passing_score'];
                    $simulation->status = $input['status'];
                    $simulation->threat_objects = $input['threat_objects'];

                    // Yeni görüntü yüklendiyse güncelle
                    if (isset($_FILES['image'])) {
                        $simulation->image_path = upload_file(
                            $_FILES['image'],
                            ['jpg', 'jpeg', 'png'],
                            10485760,
                            XRAY_PATH
                        );
                    }

                    if ($simulation->update()) {
                        json_response([
                            'success' => true,
                            'message' => 'Simülasyon başarıyla güncellendi.'
                        ]);
                    } else {
                        throw new Exception('Simülasyon güncellenirken bir hata oluştu.');
                    }
                    break;

                case 'submit_attempt':
                    // Simülasyon denemesi gönder
                    if (!isset($input['simulation_id']) || !isset($input['markers'])) {
                        throw new Exception('Eksik veri gönderildi.');
                    }

                    $simulation->id = $input['simulation_id'];
                    $attempt_data = [
                        'score' => 0,
                        'time_taken' => $input['time_taken'],
                        'correct_detections' => 0,
                        'false_positives' => 0,
                        'missed_threats' => 0,
                        'marked_positions' => $input['markers']
                    ];

                    // Sonucu hesapla
                    $sim_data = $simulation->getById($input['simulation_id']);
                    $correct_items = json_decode($sim_data['threat_objects'], true);

                    foreach ($input['markers'] as $marker) {
                        $found_match = false;
                        foreach ($correct_items as $item) {
                            if (
                                abs($marker['x'] - $item['x']) < 20 &&
                                abs($marker['y'] - $item['y']) < 20 &&
                                $marker['type'] === $item['type']
                            ) {
                                $attempt_data['correct_detections']++;
                                $attempt_data['score'] += 10;
                                $found_match = true;
                                break;
                            }
                        }
                        if (!$found_match) {
                            $attempt_data['false_positives']++;
                            $attempt_data['score'] -= 5;
                        }
                    }

                    // Kaçırılan tehditleri hesapla
                    foreach ($correct_items as $item) {
                        $found = false;
                        foreach ($input['markers'] as $marker) {
                            if (
                                abs($marker['x'] - $item['x']) < 20 &&
                                abs($marker['y'] - $item['y']) < 20 &&
                                $marker['type'] === $item['type']
                            ) {
                                $found = true;
                                break;
                            }
                        }
                        if (!$found) {
                            $attempt_data['missed_threats']++;
                            $attempt_data['score'] -= 10;
                        }
                    }

                    // Negatif puan olmamasını sağla
                    $attempt_data['score'] = max(0, $attempt_data['score']);

                    // Denemeyi kaydet
                    if ($simulation->saveAttempt($_SESSION['user_id'], $attempt_data)) {
                        json_response([
                            'success' => true,
                            'data' => $attempt_data
                        ]);
                    } else {
                        throw new Exception('Deneme kaydedilirken bir hata oluştu.');
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