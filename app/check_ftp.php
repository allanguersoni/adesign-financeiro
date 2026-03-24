<?php
$ftp_server = "ftp.allandesign.com.br";
$ftp_username = "allande134";
$ftp_userpass = "PHrxVazA556v";

$conn_id = ftp_connect($ftp_server);
if (!$conn_id) {
    die("Falha ao conectar no FTP.");
}

$login_result = ftp_login($conn_id, $ftp_username, $ftp_userpass);
if (!$login_result) {
    die("Falha no login FTP.");
}

ftp_pasv($conn_id, true);

echo "Conectado ao FTP com sucesso.\n";
echo "Conteúdo da raiz:\n";
$contents = ftp_nlist($conn_id, ".");
print_r($contents);

ftp_close($conn_id);
