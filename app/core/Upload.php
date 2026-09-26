<?php
class Upload
{
    public static function savePdf(array $file, string $candidateName = '', string $jobTitle = ''): string
    {
        $config = Config::app();
        $max = (int)$config['security']['max_upload_bytes'];
        $allowed = $config['security']['allowed_upload_mime'];

        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('Falha no upload do arquivo.');
        }
        if (($file['size'] ?? 0) > $max) {
            throw new \RuntimeException('Arquivo excede o tamanho máximo permitido.');
        }
        // Validar mimetype real usando finfo
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        if (!in_array($mime, $allowed, true)) {
            throw new \RuntimeException('Tipo de arquivo não permitido. Somente PDF.');
        }
        // Validar extensão
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext !== 'pdf') {
            throw new \RuntimeException('Extensão inválida. Envie apenas PDF.');
        }
        
        // Nome aleatório: não expõe dados do candidato e não pode ser adivinhado.
        // O nome amigável (Candidato_Vaga.pdf) é gerado apenas no download autenticado.
        $name = bin2hex(random_bytes(20)) . '.pdf';

        $destDir = STORAGE_PATH . DIRECTORY_SEPARATOR . 'resumes';
        $destPath = $destDir . DIRECTORY_SEPARATOR . $name;
        if (!is_dir($destDir)) {
            @mkdir($destDir, 0770, true);
        }
        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            throw new \RuntimeException('Não foi possível salvar o arquivo.');
        }
        @chmod($destPath, 0640);
        return $name; // armazenamos apenas o nome para posterior recuperação
    }

    public static function saveImage(array $file, string $subdir = 'logos'): string
    {
        $config = Config::app();
        $max = (int)($config['security']['max_image_bytes'] ?? (2 * 1024 * 1024));
        $allowed = $config['security']['allowed_image_mime'] ?? ['image/png','image/jpeg','image/webp'];

        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('Falha no upload da imagem.');
        }
        if (($file['size'] ?? 0) > $max) {
            throw new \RuntimeException('Imagem excede o tamanho máximo permitido.');
        }
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        if (!in_array($mime, $allowed, true)) {
            throw new \RuntimeException('Tipo de imagem não permitido. Use PNG, JPG ou WEBP.');
        }
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['png','jpg','jpeg','webp'], true)) {
            throw new \RuntimeException('Extensão inválida. Use PNG, JPG ou WEBP.');
        }
        $name = bin2hex(random_bytes(16)) . '.' . $ext;
        $destDir = PUBLIC_PATH . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $subdir;
        if (!is_dir($destDir)) {
            @mkdir($destDir, 0775, true);
        }
        $destPath = $destDir . DIRECTORY_SEPARATOR . $name;
        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            throw new \RuntimeException('Não foi possível salvar a imagem.');
        }
        return $name;
    }
}