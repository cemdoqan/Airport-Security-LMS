<?php
require_once 'vendor/autoload.php';

class Certificate {
    private $conn;
    private $table_name = "certificates";

    public $id;
    public $user_id;
    public $course_id;
    public $certificate_number;
    public $issue_date;
    public $expiry_date;
    public $status;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Sertifika oluştur
    public function create() {
        try {
            // Sertifika numarası oluştur
            $this->certificate_number = $this->generateCertificateNumber();

            $query = "INSERT INTO " . $this->table_name . " (
                        user_id, course_id, certificate_number,
                        issue_date, expiry_date, status
                    ) VALUES (
                        :user_id, :course_id, :certificate_number,
                        NOW(), DATE_ADD(NOW(), INTERVAL 2 YEAR), 'active'
                    )";

            $stmt = $this->conn->prepare($query);

            // Parametreleri bağla
            $params = [
                ":user_id" => $this->user_id,
                ":course_id" => $this->course_id,
                ":certificate_number" => $this->certificate_number
            ];

            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }

            if ($stmt->execute()) {
                $this->id = $this->conn->lastInsertId();
                return true;
            }
            return false;
        } catch (Exception $e) {
            error_log("Certificate creation error: " . $e->getMessage());
            throw $e;
        }
    }

    // PDF sertifika oluştur
    public function generatePDF($user_data, $course_data) {
        try {
            $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

            // PDF ayarları
            $pdf->SetCreator('Havalimanı Güvenlik Eğitim Platformu');
            $pdf->SetAuthor('Havalimanı Güvenlik');
            $pdf->SetTitle('Eğitim Sertifikası - ' . $course_data['title']);

            // Varsayılan başlık ve altbilgiyi kaldır
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);

            // Font ayarları
            $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
            $pdf->SetMargins(PDF_MARGIN_LEFT, 10, PDF_MARGIN_RIGHT);

            // Yeni sayfa ekle
            $pdf->AddPage('L', 'A4');

            // Sertifika HTML içeriği
            $html = $this->getCertificateHTML($user_data, $course_data);
            
            // HTML'i PDF'e ekle
            $pdf->writeHTML($html, true, false, true, false, '');

            // QR kod ekle
            $this->addQRCode($pdf);

            return $pdf->Output('sertifika.pdf', 'S');
        } catch (Exception $e) {
            error_log("PDF generation error: " . $e->getMessage());
            throw $e;
        }
    }

    // Sertifika HTML şablonu
    private function getCertificateHTML($user_data, $course_data) {
        return '
        <style>
            h1 {
                color: #003366;
                font-size: 36pt;
                text-align: center;
                margin-bottom: 20px;
            }
            .certificate-content {
                text-align: center;
                font-size: 14pt;
                line-height: 1.5;
            }
            .signature {
                margin-top: 50px;
                text-align: center;
            }
            .certificate-number {
                font-size: 10pt;
                text-align: right;
                margin-top: 30px;
            }
        </style>
        
        <h1>SERTİFİKA</h1>
        
        <div class="certificate-content">
            Bu belge,<br><br>
            <b>' . $user_data['first_name'] . ' ' . $user_data['last_name'] . '</b><br><br>
            adlı kişinin<br><br>
            <b>' . $course_data['title'] . '</b><br><br>
            eğitimini başarıyla tamamladığını belgelemektedir.<br><br>
            Veriliş Tarihi: ' . date('d.m.Y', strtotime($this->issue_date)) . '<br>
            Geçerlilik Tarihi: ' . date('d.m.Y', strtotime($this->expiry_date)) . '
        </div>
        
        <div class="signature">
            <table width="100%">
                <tr>
                    <td width="33%" align="center">
                        ________________________<br>
                        Eğitim Koordinatörü
                    </td>
                    <td width="34%" align="center">
                        ________________________<br>
                        Kurum Müdürü
                    </td>
                    <td width="33%" align="center">
                        ________________________<br>
                        Eğitmen
                    </td>
                </tr>
            </table>
        </div>
        
        <div class="certificate-number">
            Sertifika No: ' . $this->certificate_number . '
        </div>';
    }

    // QR kod ekle
    private function addQRCode($pdf) {
        $qr_data = json_encode([
            'cert_no' => $this->certificate_number,
            'issue_date' => $this->issue_date,
            'verify_url' => SITE_URL . '/verify.php?cert=' . $this->certificate_number
        ]);

        $style = [
            'border' => false,
            'vpadding' => 'auto',
            'hpadding' => 'auto',
            'fgcolor' => [0, 0, 0],
            'bgcolor' => false,
            'module_width' => 1,
            'module_height' => 1
        ];

        $pdf->write2DBarcode($qr_data, 'QRCODE,H', 250, 180, 30, 30, $style);
    }

    // Sertifika numarası oluştur
    private function generateCertificateNumber() {
        $prefix = 'CERT';
        $year = date('Y');
        $random = strtoupper(substr(uniqid(), -6));
        return $prefix . '-' . $year . '-' . $random;
    }

    // Sertifika doğrulama
    public function verify($certificate_number) {
        try {
            $query = "SELECT c.*, u.first_name, u.last_name, co.title as course_title
                     FROM " . $this->table_name . " c
                     JOIN users u ON c.user_id = u.id
                     JOIN courses co ON c.course_id = co.id
                     WHERE c.certificate_number = :certificate_number";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":certificate_number", $certificate_number);
            $stmt->execute();

            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Certificate verification error: " . $e->getMessage());
            throw $e;
        }
    }

    // Sertifikaları listele
    public function getUserCertificates($user_id) {
        try {
            $query = "SELECT c.*, co.title as course_title
                     FROM " . $this->table_name . " c
                     JOIN courses co ON c.course_id = co.id
                     WHERE c.user_id = :user_id
                     ORDER BY c.issue_date DESC";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":user_id", $user_id);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Certificate list error: " . $e->getMessage());
            throw $e;
        }
    }

    // Süresi dolmuş sertifikaları kontrol et
    public function checkExpiredCertificates() {
        try {
            $query = "UPDATE " . $this->table_name . "
                     SET status = 'expired'
                     WHERE expiry_date < CURDATE()
                     AND status = 'active'";

            $stmt = $this->conn->prepare($query);
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Certificate expiry check error: " . $e->getMessage());
            throw $e;
        }
    }

    // Sertifika yenile
    public function renew($certificate_id) {
        try {
            $query = "UPDATE " . $this->table_name . "
                     SET issue_date = NOW(),
                         expiry_date = DATE_ADD(NOW(), INTERVAL 2 YEAR),
                         status = 'active'
                     WHERE id = :certificate_id";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":certificate_id", $certificate_id);
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Certificate renewal error: " . $e->getMessage());
            throw $e;
        }
    }

    // Sertifika iptali
    public function revoke($certificate_id, $reason) {
        try {
            $query = "UPDATE " . $this->table_name . "
                     SET status = 'revoked',
                         revocation_reason = :reason,
                         revocation_date = NOW()
                     WHERE id = :certificate_id";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":certificate_id", $certificate_id);
            $stmt->bindParam(":reason", $reason);
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Certificate revocation error: " . $e->getMessage());
            throw $e;
        }
    }
}