<?php
/*
 * Add PPP Profile for Mikhmon v3
 */
session_start();
error_reporting(0);

if (!isset($_SESSION["mikhmon"])) {
    header("Location:./admin.php?id=login");
    exit();
}

$session = $_GET['session'] ?? '';
$getpool = $API->comm("/ip/pool/print");

if (isset($_POST['save'])) {
    $name = trim($_POST['name']);
    $local_addr = trim($_POST['local_address']);
    $remote_addr = trim($_POST['remote_address']);
    $rate_limit = trim($_POST['rate_limit']);
    $only_one = $_POST['only_one'] ?? 'default';
    $dns_server = trim($_POST['dns_server']);
    $comment = trim($_POST['comment']);

    if (!empty($name)) {
        $params = array(
            "name" => $name,
            "only-one" => $only_one,
        );

        if (!empty($local_addr)) $params["local-address"] = $local_addr;
        if (!empty($remote_addr)) $params["remote-address"] = $remote_addr;
        if (!empty($rate_limit)) $params["rate-limit"] = $rate_limit;
        if (!empty($dns_server)) $params["dns-server"] = $dns_server;
        if (!empty($comment)) $params["comment"] = $comment;

        $API->comm("/ppp/profile/add", $params);

        echo "<script>window.location='./?ppp=profiles&session=" . $session . "'</script>";
        exit();
    }
}
?>

<div class="row">
  <div class="col-8">
    <div class="card">
      <div class="card-header">
        <h3><i class="fa fa-plus-square"></i> Tambah PPP Profile</h3>
      </div>
      <div class="card-body">
        <form autocomplete="off" method="post" action="">
          <table class="table">
            <tr>
              <td style="width: 220px;">Nama Profile</td>
              <td><input type="text" class="form-control" name="name" required placeholder="Contoh: 10Mbps, PAKET-A, ISOLIR"></td>
            </tr>
            <tr>
              <td>Local Address</td>
              <td><input type="text" class="form-control" name="local_address" placeholder="10.10.10.1 atau nama IP Pool"></td>
            </tr>
            <tr>
              <td>Remote Address / Pool</td>
              <td>
                <input type="text" class="form-control" name="remote_address" placeholder="Pilih pool di bawah atau ketik manual" list="pool_list">
                <datalist id="pool_list">
                  <?php foreach ($getpool as $pool): ?>
                    <option value="<?= htmlspecialchars($pool['name']); ?>">
                  <?php endforeach; ?>
                </datalist>
              </td>
            </tr>
            <tr>
              <td>Rate Limit (Rx/Tx)</td>
              <td><input type="text" class="form-control" name="rate_limit" placeholder="Contoh: 5M/10M (Upload/Download)"></td>
            </tr>
            <tr>
              <td>DNS Server</td>
              <td><input type="text" class="form-control" name="dns_server" placeholder="8.8.8.8, 1.1.1.1"></td>
            </tr>
            <tr>
              <td>Only One (1 User 1 Session)</td>
              <td>
                <select class="form-control" name="only_one">
                  <option value="default">default</option>
                  <option value="yes" selected>yes</option>
                  <option value="no">no</option>
                </select>
              </td>
            </tr>
            <tr>
              <td>Comment</td>
              <td><input type="text" class="form-control" name="comment" placeholder="Keterangan profile"></td>
            </tr>
            <tr>
              <td></td>
              <td>
                <button type="submit" name="save" class="btn bg-primary"><i class="fa fa-save"></i> Simpan Profile</button>
                <a href="./?ppp=profiles&session=<?= $session; ?>" class="btn bg-secondary">Batal</a>
              </td>
            </tr>
          </table>
        </form>
      </div>
    </div>
  </div>
</div>
