<?php
/**
 * Gestione Immagini Prodotti
 * Assistente Farmacia Panel
 */

require_once __DIR__ . '/../config/database.php';

/**
 * Classe per la gestione delle immagini dei prodotti
 */
class ProductImageManager {
    
    private $uploadDir;
    private $maxWidth = 800;
    private $maxHeight = 800;
    private $quality = 85;
    private $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    private $maxFileSize = 5 * 1024 * 1024; // 5MB
    
    public function __construct() {
        $this->uploadDir = __DIR__ . '/../uploads/products/';
        $this->ensureUploadDirectory();
    }
    
    /**
     * Assicura che la directory di upload esista
     */
    private function ensureUploadDirectory() {
        if (!file_exists($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
        
        // Crea sottodirectory per categorie
        $categories = ['antidolorifici', 'antinfiammatori', 'integratori', 'antibiotici', 'cardiovascolari', 'dermatologici', 'gastroenterologici', 'respiratori', 'vitamine', 'generico'];
        foreach ($categories as $category) {
            $categoryDir = $this->uploadDir . $category . '/';
            if (!file_exists($categoryDir)) {
                mkdir($categoryDir, 0755, true);
            }
        }
    }
    
    /**
     * Genera un'immagine placeholder per un prodotto
     */
    public function generatePlaceholderImage($productName, $category = 'generico', $sku = '') {
        $width = 400;
        $height = 400;
        
        // Crea immagine base
        $image = imagecreatetruecolor($width, $height);
        
        // Colori
        $bgColor = imagecolorallocate($image, 240, 240, 240);
        $textColor = imagecolorallocate($image, 100, 100, 100);
        $accentColor = imagecolorallocate($image, 44, 62, 80);
        
        // Sfondo
        imagefill($image, 0, 0, $bgColor);
        
        // Bordo
        imagerectangle($image, 0, 0, $width-1, $height-1, $accentColor);
        
        // Icona farmacia
        $this->drawPharmacyIcon($image, $width/2, $height/2 - 40, 60, $accentColor);
        
        // Ottieni font
        $fontPath = $this->getFontPath();
        
        // Nome prodotto
        $fontSize = 16;
        $text = $this->truncateText($productName, 20);
        $textBox = imagettfbbox($fontSize, 0, $fontPath, $text);
        $textWidth = $textBox[4] - $textBox[0];
        $textX = ($width - $textWidth) / 2;
        $textY = $height/2 + 40;
        
        imagettftext($image, $fontSize, 0, $textX, $textY, $textColor, $fontPath, $text);
        
        // SKU
        if ($sku) {
            $fontSize = 12;
            $skuText = "SKU: " . $sku;
            $textBox = imagettfbbox($fontSize, 0, $fontPath, $skuText);
            $textWidth = $textBox[4] - $textBox[0];
            $textX = ($width - $textWidth) / 2;
            $textY = $height/2 + 70;
            
            imagettftext($image, $fontSize, 0, $textX, $textY, $textColor, $fontPath, $skuText);
        }
        
        // Categoria
        $fontSize = 10;
        $categoryText = strtoupper($category);
        $textBox = imagettfbbox($fontSize, 0, $fontPath, $categoryText);
        $textWidth = $textBox[4] - $textBox[0];
        $textX = ($width - $textWidth) / 2;
        $textY = $height - 20;
        
        imagettftext($image, $fontSize, 0, $textX, $textY, $accentColor, $fontPath, $categoryText);
        
        // Salva immagine
        $filename = 'placeholder_' . uniqid() . '.png';
        $filepath = $this->getCategoryPath($category) . $filename;
        
        imagepng($image, $filepath, 9);
        imagedestroy($image);
        
        return $this->getRelativePath($filepath);
    }
    
    /**
     * Disegna icona farmacia
     */
    private function drawPharmacyIcon($image, $x, $y, $size, $color) {
        // Croce farmaceutica
        $thickness = 3;
        
        // Linea verticale
        imageline($image, $x, $y - $size/2, $x, $y + $size/2, $color);
        
        // Linea orizzontale
        imageline($image, $x - $size/2, $y, $x + $size/2, $y, $color);
        
        // Cerchio esterno
        imageellipse($image, $x, $y, $size, $size, $color);
    }
    
    /**
     * Processa e salva un'immagine caricata
     */
    public function processUploadedImage($file, $category = 'generico') {
        // Validazione
        if (!$this->validateFile($file)) {
            throw new Exception('File non valido');
        }
        
        // Ottieni informazioni immagine
        $imageInfo = getimagesize($file['tmp_name']);
        if (!$imageInfo) {
            throw new Exception('Impossibile leggere l\'immagine');
        }
        
        // Carica immagine
        $sourceImage = $this->loadImage($file['tmp_name'], $imageInfo[2]);
        if (!$sourceImage) {
            throw new Exception('Impossibile caricare l\'immagine');
        }
        
        // Ridimensiona
        $resizedImage = $this->resizeImage($sourceImage, $imageInfo[0], $imageInfo[1]);
        
        // Genera nome file
        $extension = $this->getExtensionFromMime($imageInfo['mime']);
        $filename = 'product_' . uniqid() . '.' . $extension;
        $filepath = $this->getCategoryPath($category) . $filename;
        
        // Salva immagine
        $this->saveImage($resizedImage, $filepath, $extension);
        
        // Pulisci memoria
        imagedestroy($sourceImage);
        imagedestroy($resizedImage);
        
        return $this->getRelativePath($filepath);
    }
    
    /**
     * Valida file caricato
     */
    private function validateFile($file) {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return false;
        }
        
        if (!in_array($file['type'], $this->allowedTypes)) {
            return false;
        }
        
        if ($file['size'] > $this->maxFileSize) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Carica immagine da file
     */
    private function loadImage($filepath, $type) {
        switch ($type) {
            case IMAGETYPE_JPEG:
                return imagecreatefromjpeg($filepath);
            case IMAGETYPE_PNG:
                return imagecreatefrompng($filepath);
            case IMAGETYPE_GIF:
                return imagecreatefromgif($filepath);
            default:
                return false;
        }
    }
    
    /**
     * Ridimensiona immagine mantenendo proporzioni
     */
    private function resizeImage($sourceImage, $sourceWidth, $sourceHeight) {
        // Calcola nuove dimensioni
        $ratio = min($this->maxWidth / $sourceWidth, $this->maxHeight / $sourceHeight);
        
        if ($ratio >= 1) {
            // Immagine già più piccola, non ridimensionare
            return $sourceImage;
        }
        
        $newWidth = round($sourceWidth * $ratio);
        $newHeight = round($sourceHeight * $ratio);
        
        // Crea nuova immagine
        $resizedImage = imagecreatetruecolor($newWidth, $newHeight);
        
        // Mantieni trasparenza per PNG
        imagealphablending($resizedImage, false);
        imagesavealpha($resizedImage, true);
        $transparent = imagecolorallocatealpha($resizedImage, 255, 255, 255, 127);
        imagefill($resizedImage, 0, 0, $transparent);
        
        // Ridimensiona
        imagecopyresampled($resizedImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $sourceWidth, $sourceHeight);
        
        return $resizedImage;
    }
    
    /**
     * Salva immagine
     */
    private function saveImage($image, $filepath, $extension) {
        // Verifica che la directory esista
        $directory = dirname($filepath);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
        
        // Verifica che la directory sia scrivibile
        if (!is_writable($directory)) {
            throw new Exception('Directory non scrivibile');
        }
        
        // Salva l'immagine
        switch ($extension) {
            case 'jpg':
            case 'jpeg':
                imagejpeg($image, $filepath, $this->quality);
                break;
            case 'png':
                imagepng($image, $filepath, 9);
                break;
            case 'gif':
                imagegif($image, $filepath);
                break;
        }
    }
    
    /**
     * Ottieni estensione da MIME type
     */
    private function getExtensionFromMime($mime) {
        $mimeMap = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif'
        ];
        
        return $mimeMap[$mime] ?? 'jpg';
    }
    
    /**
     * Ottieni percorso categoria
     */
    private function getCategoryPath($category) {
        $category = strtolower($category);
        $category = preg_replace('/[^a-z0-9]/', '', $category);
        
        if (empty($category)) {
            $category = 'generico';
        }
        
        return $this->uploadDir . $category . '/';
    }
    
    /**
     * Ottieni percorso relativo
     */
    private function getRelativePath($absolutePath) {
        $basePath = __DIR__ . '/../';
        $relativePath = str_replace($basePath, '', $absolutePath);
        return $relativePath;
    }
    
    /**
     * Elimina immagine
     */
    public function deleteImage($imagePath) {
        if (empty($imagePath)) {
            return true;
        }
        
        $absolutePath = __DIR__ . '/../' . $imagePath;
        
        if (file_exists($absolutePath)) {
            return unlink($absolutePath);
        }
        
        return true;
    }
    
    /**
     * Genera thumbnail
     */
    public function generateThumbnail($imagePath, $width = 150, $height = 150) {
        if (empty($imagePath)) {
            return null;
        }
        
        $absolutePath = __DIR__ . '/../' . $imagePath;
        
        if (!file_exists($absolutePath)) {
            return null;
        }
        
        $imageInfo = getimagesize($absolutePath);
        if (!$imageInfo) {
            return null;
        }
        
        $sourceImage = $this->loadImage($absolutePath, $imageInfo[2]);
        if (!$sourceImage) {
            return null;
        }
        
        // Crea thumbnail quadrato
        $thumbnail = imagecreatetruecolor($width, $height);
        
        // Mantieni trasparenza
        imagealphablending($thumbnail, false);
        imagesavealpha($thumbnail, true);
        $transparent = imagecolorallocatealpha($thumbnail, 255, 255, 255, 127);
        imagefill($thumbnail, 0, 0, $transparent);
        
        // Calcola dimensioni per crop quadrato
        $sourceWidth = $imageInfo[0];
        $sourceHeight = $imageInfo[1];
        
        if ($sourceWidth > $sourceHeight) {
            $cropSize = $sourceHeight;
            $cropX = ($sourceWidth - $cropSize) / 2;
            $cropY = 0;
        } else {
            $cropSize = $sourceWidth;
            $cropX = 0;
            $cropY = ($sourceHeight - $cropSize) / 2;
        }
        
        // Ridimensiona
        imagecopyresampled($thumbnail, $sourceImage, 0, 0, $cropX, $cropY, $width, $height, $cropSize, $cropSize);
        
        // Salva thumbnail
        $thumbnailPath = str_replace('.', '_thumb.', $absolutePath);
        $extension = pathinfo($absolutePath, PATHINFO_EXTENSION);
        $this->saveImage($thumbnail, $thumbnailPath, $extension);
        
        imagedestroy($sourceImage);
        imagedestroy($thumbnail);
        
        return $this->getRelativePath($thumbnailPath);
    }
    
    /**
     * Ottieni percorso font
     */
    private function getFontPath() {
        $customFont = __DIR__ . '/../assets/fonts/arial.ttf';
        
        if (file_exists($customFont)) {
            return $customFont;
        }
        
        // Font di sistema
        $systemFonts = [
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
            '/System/Library/Fonts/Arial.ttf', // macOS
            'C:/Windows/Fonts/arial.ttf' // Windows
        ];
        
        foreach ($systemFonts as $font) {
            if (file_exists($font)) {
                return $font;
            }
        }
        
        // Fallback: usa font di sistema generico
        return '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf';
    }
    
    /**
     * Tronca testo
     */
    private function truncateText($text, $length) {
        if (strlen($text) <= $length) {
            return $text;
        }
        
        return substr($text, 0, $length - 3) . '...';
    }
    
    /**
     * Ottieni URL pubblico dell'immagine
     */
    public function getImageUrl($imagePath) {
        if (empty($imagePath)) {
            return 'assets/images/default-product.png';
        }
        
        return $imagePath;
    }
    
    /**
     * Ottieni URL pubblico del thumbnail
     */
    public function getThumbnailUrl($imagePath) {
        if (empty($imagePath)) {
            return 'assets/images/default-product-thumb.png';
        }
        
        $thumbnailPath = str_replace('.', '_thumb.', $imagePath);
        
        if (file_exists(__DIR__ . '/../' . $thumbnailPath)) {
            return $thumbnailPath;
        }
        
        return $imagePath;
    }
}

/**
 * Funzioni helper globali
 */

/**
 * Genera immagine placeholder per prodotto
 */
function generateProductPlaceholder($productName, $category = 'generico', $sku = '') {
    $manager = new ProductImageManager();
    return $manager->generatePlaceholderImage($productName, $category, $sku);
}

/**
 * Processa immagine caricata
 */
function processProductImage($file, $category = 'generico') {
    $manager = new ProductImageManager();
    return $manager->processUploadedImage($file, $category);
}

/**
 * Elimina immagine prodotto
 */
function deleteProductImage($imagePath) {
    $manager = new ProductImageManager();
    return $manager->deleteImage($imagePath);
}

/**
 * Genera thumbnail
 */
function generateProductThumbnail($imagePath) {
    $manager = new ProductImageManager();
    return $manager->generateThumbnail($imagePath);
}

/**
 * Ottieni URL immagine
 */
function getProductImageUrl($imagePath) {
    $manager = new ProductImageManager();
    return $manager->getImageUrl($imagePath);
}

/**
 * Ottieni URL thumbnail
 */
function getProductThumbnailUrl($imagePath) {
    $manager = new ProductImageManager();
    return $manager->getThumbnailUrl($imagePath);
}
?> 