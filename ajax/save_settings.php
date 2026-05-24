<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';

try {
    $site_name = isset($_POST['site_name']) ? trim($_POST['site_name']) : 'Arena Stream';
    
    // Atualizar site_name
    dbUpsertSetting($pdo, 'site_name', $site_name);
    
    // Tratar upload da imagem offline
    if (isset($_FILES['offline_image']) && $_FILES['offline_image']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['offline_image']['tmp_name'];
        $fileName = $_FILES['offline_image']['name'];
        $fileSize = $_FILES['offline_image']['size'];
        $fileType = $_FILES['offline_image']['type'];
        
        $fileNameCmps = explode(".", $fileName);
        $fileExtension = strtolower(end($fileNameCmps));
        
        // Extensões permitidas
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        if (in_array($fileExtension, $allowedExtensions)) {
            $uploadFileDir = __DIR__ . '/../assets/images/';
            if (!is_dir($uploadFileDir)) {
                mkdir($uploadFileDir, 0755, true);
            }
            
            $newFileName = 'offline_image.' . $fileExtension;
            $dest_path = $uploadFileDir . $newFileName;
            
            if (move_uploaded_file($fileTmpPath, $dest_path)) {
                $dbPath = 'assets/images/' . $newFileName;
                dbUpsertSetting($pdo, 'offline_image', $dbPath);
            } else {
                echo json_encode(['success' => false, 'error' => 'Houve um erro ao salvar a imagem no diretório de destino.']);
                exit;
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Extensão de arquivo não permitida. Apenas JPG, PNG, WEBP e GIF são suportados.']);
            exit;
        }
    }

    // Tratar upload do plano de fundo do banner do hero
    if (isset($_FILES['hero_banner_image']) && $_FILES['hero_banner_image']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['hero_banner_image']['tmp_name'];
        $fileName = $_FILES['hero_banner_image']['name'];
        $fileSize = $_FILES['hero_banner_image']['size'];
        $fileType = $_FILES['hero_banner_image']['type'];
        
        $fileNameCmps = explode(".", $fileName);
        $fileExtension = strtolower(end($fileNameCmps));
        
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        if (in_array($fileExtension, $allowedExtensions)) {
            $uploadFileDir = __DIR__ . '/../assets/images/';
            if (!is_dir($uploadFileDir)) {
                mkdir($uploadFileDir, 0755, true);
            }
            
            $newFileName = 'hero_banner.' . $fileExtension;
            $dest_path = $uploadFileDir . $newFileName;
            
            if (move_uploaded_file($fileTmpPath, $dest_path)) {
                $dbPath = 'assets/images/' . $newFileName;
                dbUpsertSetting($pdo, 'hero_banner_image', $dbPath);
            } else {
                echo json_encode(['success' => false, 'error' => 'Houve um erro ao salvar a imagem do banner no diretório de destino.']);
                exit;
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Extensão de imagem do banner não permitida. Apenas JPG, PNG, WEBP e GIF são suportados.']);
            exit;
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Configurações salvas com sucesso!'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao salvar configurações: ' . $e->getMessage()
    ]);
}
?>
