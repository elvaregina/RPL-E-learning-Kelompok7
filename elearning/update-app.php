<?php

/**
 * Class untuk update aplikasi
 *
 * @package   e-Learning Dokumenary Net
 * @author    Almazari <almazary@gmail.com>
 * @copyright Copyright (c) 2013 - 2016, Dokumenary Net.
 * @since     1.8
 * @link      http://dokumenary.net
 */

class updateElearning
{
    var $db_config;
    var $link;
    var $config;
    var $version;

    function __construct()
    {
        define("BASEPATH", 1);

        $this->loadConfig();
        $this->connectDB();
        $this->checkSession();
    }

    function doUpdate()
    {
        # cek file
        $path_folder      = './userfiles/updates/' . $this->version . '/';
        $path_file_update = $path_folder . $this->version . '.zip';
        if (!is_file($path_file_update)) {
            throw new Exception("File update tidak ditemukan!");
        }

        $zip = new ZipArchive;
        if ($zip->open($path_file_update) === TRUE) {
            $zip->extractTo($path_folder);
            $zip->close();
        } else {
            throw new Exception("Gagal extract file update!");
        }

        # baca path.json
        $json_path = $path_folder . 'path.json';
        if (!is_file($json_path)) {
            throw new Exception("File update (path.json) tidak lengkap!");
        }

        $str_json = file_get_contents($json_path);
        if (empty($str_json)) {
            throw new Exception("File update tidak lengkap!");
        }

        $json = json_decode($str_json, 1);
        foreach ($json as $j_val) {
            $destinantion = $j_val[0];
            $key_do       = $j_val[1]; # 1: dibutuhkan, 2: hapus yang ada

            $path_tujuan = './' . $destinantion;

            $split_path = explode("/", $destinantion);
            $file_name  = end($split_path);
            $file_path  = $path_folder . $destinantion;

            # cek file disertakan tidak, kalo perintahnya hapus abaikan
            if (!is_file($file_path) AND $key_do != 2) {
                throw new Exception("File {$file_path} tidak tersedia!");
            }

            # jika file belum ada dicopy saja
            if (!is_file($path_tujuan)) {
                if ($key_do == 1) {
                    try {
                        # cek directorey
                        $split_tujuan = explode('/', $path_tujuan);
                        foreach ($split_tujuan as $path_tujuan_dir) {
                            if ($path_tujuan_dir == ".") {
                                $temp_path = "./";
                                continue;
                            }

                            if ($path_tujuan_dir == end($split_tujuan)) {
                                break;
                            }

                            if (!is_dir($temp_path . $path_tujuan_dir)) {
                                mkdir($temp_path . $path_tujuan_dir);
                            }

                            $temp_path = $temp_path . $path_tujuan_dir . "/";
                        }

                        copy($file_path, $path_tujuan);
                    } catch (Exception $e) {
                        throw new Exception("File {$path_tujuan} update gagal dipindah!, error: " . $e->getMessage());
                    }
                }
            } else {
                if ($key_do == 2) {
                    try {
                        unlink($path_tujuan);
                    } catch (Exception $e) {
                        throw new Exception("File {$path_tujuan} gagal dihapus!, error: " . $e->getMessage());
                    }
                }
                else {
                    # rename dulu yang sebelumnya
                    $rename_path = $path_tujuan . '.bak';
                    try {
                        rename($path_tujuan, $rename_path);

                        # pindahkan
                        copy($file_path, $path_tujuan);
                    } catch (Exception $e) {
                        # kembalikan
                        rename($rename_path, $path_tujuan);

                        throw new Exception("File {$file_name} gagal diperbaharui!, error: " . $e->getMessage());
                    }

                    # hapus bak
                    unlink($rename_path);
                }
            }
        }

        # hapus session
        $this->deleteSession();

        # hapus file update
        $this->rrmdir($path_folder);

        # hapus cache twig
        $this->rrmdir("./application/cache/twig/");
    }

    function rrmdir($dir) {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (is_dir($dir . "/" . $object)) {
                        $this->rrmdir($dir . "/" . $object);
                    } else {
                        unlink($dir . "/" . $object);
                    }
                }
            }

            rmdir($dir);
        }
    }

    function checkSession()
    {
        $table    = $this->db_config['default']['dbprefix'] . 'field_tambahan';
        $field_id = "session-updates";

        $sql    = "SELECT * FROM $table WHERE id = '$field_id'";
        $query  = mysqli_query($this->link, $sql);
        $result = mysqli_fetch_assoc($query);

        if (empty($result['value'])) {
            throw new Exception("Session update tidak ditemukan!");
        }

        $result_val = json_decode($result['value'], 1);
        $this->version = $result_val['version'];
    }

    function deleteSession()
    {
        $table    = $this->db_config['default']['dbprefix'] . 'field_tambahan';
        $field_id = "session-updates";

        $sql = "UPDATE $table SET `value` = '' WHERE id = '$field_id'";
        mysqli_query($this->link, $sql);
    }

    function connectDB()
    {
        # koneksi ke database
        $path_config_db = './application/config/database.php';
        if (!is_file($path_config_db)) {
            throw new Exception("File config database.php tidak ditemukan!");
        }

        require_once($path_config_db);

        $link = mysqli_connect($db['default']['hostname'], $db['default']['username'], $db['default']['password']);
        if (!$link) {
            throw new Exception('Failed to connect to the server: ' . mysqli_connect_error());
        }
        elseif (!mysqli_select_db($link, $db['default']['database'])) {
            throw new Exception('Failed to connect to the database: ' . mysqli_error($link));
        }

        $this->db_config = $db;
        $this->link = $link;
    }

    function loadConfig()
    {
        $path_config = './application/config/config.php';
        if (!is_file($path_config)) {
            throw new Exception("File config config.php tidak ditemukan!");
        }

        require_once($path_config);
    }
}

if (!empty($_GET['doupdate'])) {
    try {
        $src_update = new updateElearning();
        $src_update->doUpdate();
    } catch (Exception $e) {
        echo $e->getMessage();die;
    }

    echo "1";
    die;
}

?>

<!DOCTYPE html>
<html>
<head>
    <title>Update aplikasi e-Learning by dokumenary.net</title>
</head>
<body>
    <h3 id="title-update">Process update...</h3>
    <div id="result-update"></div>

    <script src="./assets/comp/jquery/jquery.min.js" type="text/javascript"></script>
    <script type="text/javascript">
        $("#result-update").html('<img src="./assets/images/loading.gif" style="width:30px;">');

        setTimeout(function() {
            $.ajax({
                method: "GET",
                url: "update-app.php?doupdate=1",
                success: function(data) {
                    if (data == 1) {
                        location.href = "index.php";
                    } else {
                        $("#title-update").remove();
                        $("#result-update").html(data);
                    }
                }
            });
        }, 2000);
    </script>
</body>
</html>
