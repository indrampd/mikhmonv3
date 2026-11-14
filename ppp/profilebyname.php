<?php
/*
 * Edit PPP Profile for Mikhmon v3
 */
session_start();
error_reporting(0);

if (!isset($_SESSION["mikhmon"])) {
    header("Location:./admin.php?id=login");
    exit();
}

$session = $_GET['session'] ?? '';
$pname = $_GET['name'] ?? '';

$getprof = $API->comm("/ppp/profile/print", array("?name" => "$pname"));
if (empty($getprof)) {
    echo "<script>alert('Profile tidak ditemukan!'); window.location='./?ppp=profiles&session=" . $session . "';</script>";
    exit();
}

$prof = $getprof[0];
$getpool = $API->comm("/ip/pool/print");

if (isset($_POST['save'])) {
    $local_addr = trim($_POST['local_address']);
    $remote_addr = trim($_POST['remote_address']);
    $rate_limit = trim($_POST['rate_limit']);
    $only_one = $_POST['only_one'] ?? 'default';
    $dns_server = trim($_POST['dns_server']);
    $comment = trim($_POST['comment']);

    $params = array(
        ".id" => $prof['.id'],
        "local-address" => $local_addr,
        "remote-address" => $remote_addr,
        "rate-limit" => $rate_limit,
        "only-one" => $only_one,
        "dns-server" => $dns_server,
        "comment" => $comment,
    );

    $result = routeros_safe_set($API, "/ppp/profile/set", $params);
    if (!$result["ok"]) {
        $_SESSION["ppp_edit_error"] = $result["error"];
        header("Location: ./?name=" . urlencode($pname) . "&session=" . urlencode($session));
        exit();
    }
    unset($_SESSION["ppp_edit_error"]);

    echo "<script>window.location='./?ppp=profiles&session=" . $session . "'</script>";
    exit();
}
?>

<div class="row">
  <div class="col-8">
    <div class="card">
      <div class="card-header">
        <h3><i class="fa fa-edit"></i> Edit PPP Profile: <strong><?= htmlspecialchars($prof['name']); ?></strong></h3>
      </div>
      <div class="card-body">
        <?php if (!empty($_SESSION["ppp_edit_error"])): ?>
          <div class="alert bg-danger pd-10 radius-3 mr-b-10">
            <i class="fa fa-ban"></i> RouterOS menolak perubahan:
            <strong><?= htmlspecialchars($_SESSION["ppp_edit_error"]); ?></strong>
          </div>
          <?php unset($_SESSION["ppp_edit_error"]); ?>
        <?php endif; ?>
        <form autocomplete="off" method="post" action="">
          <table class="table">
            <tr>
              <td style="width: 220px;">Nama Profile</td>
              <td><strong><?= htmlspecialchars($prof['name']); ?></strong></td>
            </tr>
            <tr>
              <td>Local Address</td>
              <td><input type="text" class="form-control" name="local_address" value="<?= htmlspecialchars($prof['local-address'] ?? ''); ?>"></td>
            </tr>
            <tr>
              <td>Remote Address / Pool</td>
              <td>
                <input type="text" class="form-control" name="remote_address" value="<?= htmlspecialchars($prof['remote-address'] ?? ''); ?>" list="pool_list">
                <datalist id="pool_list">
                  <?php foreach ($getpool as $pool): ?>
                    <option value="<?= htmlspecialchars($pool['name']); ?>">
                  <?php endforeach; ?>
                </datalist>
              </td>
            </tr>
            <tr>
              <td>Rate Limit (Rx/Tx)</td>
              <td><input type="text" class="form-control" name="rate_limit" value="<?= htmlspecialchars($prof['rate-limit'] ?? ''); ?>" placeholder="Contoh: 5M/10M"></td>
            </tr>
            <tr>
              <td>DNS Server</td>
              <td><input type="text" class="form-control" name="dns_server" value="<?= htmlspecialchars($prof['dns-server'] ?? ''); ?>"></td>
            </tr>
            <tr>
              <td>Only One</td>
              <td>
                <select class="form-control" name="only_one">
                  <?php foreach (array('default', 'yes', 'no') as $opt): ?>
                    <option value="<?= $opt; ?>" <?= (($prof['only-one'] ?? '') === $opt) ? 'selected' : ''; ?>><?= $opt; ?></option>
                  <?php endforeach; ?>
                </select>
              </td>
            </tr>
            <tr>
              <td>Comment</td>
              <td><input type="text" class="form-control" name="comment" value="<?= htmlspecialchars($prof['comment'] ?? ''); ?>"></td>
            </tr>
            <tr>
              <td></td>
              <td>
                <button type="submit" name="save" class="btn bg-primary"><i class="fa fa-save"></i> Perbarui Profile</button>
                <a href="./?ppp=profiles&session=<?= $session; ?>" class="btn bg-secondary">Batal</a>
              </td>
            </tr>
          </table>
        </form>
      </div>
    </div>
  </div>
</div>
