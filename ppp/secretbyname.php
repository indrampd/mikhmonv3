<?php
/*
 * Edit PPPoE / PPP Secret for Mikhmon v3
 */
session_start();
error_reporting(0);

if (!isset($_SESSION["mikhmon"])) {
    header("Location:./admin.php?id=login");
    exit();
}

$session = $_GET['session'] ?? '';
$secretbyname = $_GET['secret'] ?? '';

$getsecret = $API->comm("/ppp/secret/print", array("?name" => "$secretbyname"));
if (empty($getsecret)) {
    echo "<script>alert('User tidak ditemukan!'); window.location='./?ppp=secrets&session=" . $session . "';</script>";
    exit();
}

$sec = $getsecret[0];
$getprofile = $API->comm("/ppp/profile/print");

if (isset($_POST['save'])) {
    $password = trim($_POST['pass']);
    $service = $_POST['service'] ?? 'pppoe';
    $profile = $_POST['profile'] ?? 'default';
    $local_addr = trim($_POST['local_address']);
    $remote_addr = trim($_POST['remote_address']);
    $comment = trim($_POST['comment']);
    $disabled = ($_POST['disabled'] === 'yes') ? 'yes' : 'no';

    // RouterOS rejects the whole request when an address argument is empty
    // ("invalid value for argument address"), which made every save silently
    // fail. Empty optional values are sent as an explicit clear instead.
    $params = array(
        ".id" => $sec['.id'],
        "password" => $password,
        "service" => $service,
        "profile" => routeros_profile_id($API, 'ppp', $profile),
        "disabled" => $disabled,
        "comment" => $comment,
    );
    foreach (array("local-address" => $local_addr, "remote-address" => $remote_addr) as $key => $val) {
        $params[$key] = ($val === "" || $val === null) ? "0.0.0.0/0" : $val;
        if ($val === "") {
            unset($params[$key]);
        }
    }

    $res = $API->comm("/ppp/secret/set", $params);
    if (is_array($res) && !empty($res["!trap"])) {
        $err = "";
        foreach ($res["!trap"] as $trap) {
            $err .= ($trap["message"] ?? "unknown error") . " ";
        }
        $_SESSION["ppp_edit_error"] = trim($err);
        header("Location: ./?secret=" . urlencode($secretbyname) . "&session=" . urlencode($session));
        exit();
    }
    unset($_SESSION["ppp_edit_error"]);

    // If active, disconnect to apply immediately if profile/password changed
    if (isset($_POST['disconnect_active'])) {
        $check_active = $API->comm("/ppp/active/print", array("?name" => "$secretbyname"));
        if (!empty($check_active)) {
            $API->comm("/ppp/active/remove", array(".id" => $check_active[0]['.id']));
        }
    }

    echo "<script>window.location='./?ppp=secrets&session=" . $session . "'</script>";
    exit();
}
?>

<div class="row">
  <div class="col-8">
    <div class="card">
      <div class="card-header">
        <h3><i class="fa fa-edit"></i> Edit Secret: <strong><?= htmlspecialchars($sec['name']); ?></strong></h3>
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
              <td style="width: 200px;">Username</td>
              <td><strong><?= htmlspecialchars($sec['name']); ?></strong></td>
            </tr>
            <tr>
              <td>Password</td>
              <td><input type="text" class="form-control" name="pass" required value="<?= htmlspecialchars($sec['password'] ?? ''); ?>"></td>
            </tr>
            <tr>
              <td>Service</td>
              <td>
                <select class="form-control" name="service">
                  <?php foreach (array('pppoe', 'any', 'l2tp', 'pptp', 'ovpn') as $srv): ?>
                    <option value="<?= $srv; ?>" <?= ($sec['service'] === $srv) ? 'selected' : ''; ?>><?= $srv; ?></option>
                  <?php endforeach; ?>
                </select>
              </td>
            </tr>
            <tr>
              <td>Profile</td>
              <td>
                <select class="form-control" name="profile">
                  <?php foreach ($getprofile as $p): ?>
                    <option value="<?= htmlspecialchars($p['name']); ?>" <?= ($sec['profile'] === $p['name']) ? 'selected' : ''; ?>>
                      <?= htmlspecialchars($p['name']); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </td>
            </tr>
            <tr>
              <td>Local Address</td>
              <td><input type="text" class="form-control" name="local_address" value="<?= htmlspecialchars($sec['local-address'] ?? ''); ?>"></td>
            </tr>
            <tr>
              <td>Remote Address / Static IP</td>
              <td><input type="text" class="form-control" name="remote_address" value="<?= htmlspecialchars($sec['remote-address'] ?? ''); ?>"></td>
            </tr>
            <tr>
              <td>Comment</td>
              <td><input type="text" class="form-control" name="comment" value="<?= htmlspecialchars($sec['comment'] ?? ''); ?>"></td>
            </tr>
            <tr>
              <td>Status</td>
              <td>
                <select class="form-control" name="disabled">
                  <option value="no" <?= ($sec['disabled'] !== 'true') ? 'selected' : ''; ?>>Enable (Aktif)</option>
                  <option value="yes" <?= ($sec['disabled'] === 'true') ? 'selected' : ''; ?>>Disable (Nonaktif)</option>
                </select>
              </td>
            </tr>
            <tr>
              <td>Auto Kick Sesi Aktif</td>
              <td>
                <label style="font-weight: normal; cursor: pointer;">
                  <input type="checkbox" name="disconnect_active" value="1" checked> Putuskan sesi aktif agar perubahan langsung berlaku
                </label>
              </td>
            </tr>
            <tr>
              <td></td>
              <td>
                <button type="submit" name="save" class="btn bg-primary"><i class="fa fa-save"></i> Perbarui Secret</button>
                <a href="./?ppp=secrets&session=<?= $session; ?>" class="btn bg-secondary">Batal</a>
              </td>
            </tr>
          </table>
        </form>
      </div>
    </div>
  </div>
</div>
