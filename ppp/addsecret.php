<?php
/*
 * Add PPPoE / PPP Secret for Mikhmon v3
 */
session_start();
error_reporting(0);
require_once dirname(__DIR__) . '/lib/billing.class.php';

if (!isset($_SESSION["mikhmon"])) {
    header("Location:./admin.php?id=login");
    exit();
}

$getprofile = $API->comm("/ppp/profile/print");

if (isset($_POST['save'])) {
    $name = trim($_POST['name']);
    $password = trim($_POST['pass']);
    $service = $_POST['service'] ?? 'pppoe';
    $profile_name = trim($_POST['profile'] ?? 'default');
    // Send the profile id: a name that partially matches several profiles is
    // rejected by RouterOS with "ambiguous value of profile".
    $profile = $profile_name;
    $prof_lookup = $API->comm("/ppp/profile/print", array("?name" => $profile_name));
    if (is_array($prof_lookup) && !empty($prof_lookup[0]['.id'])) {
        $profile = $prof_lookup[0]['.id'];
    }
    $local_addr = trim($_POST['local_address']);
    $remote_addr = trim($_POST['remote_address']);
    $comment = trim($_POST['comment']);
    $comment = MikhmonBilling::syncComment($comment, $profile_name, true);
    $disabled = ($_POST['disabled'] === 'yes') ? 'yes' : 'no';

    if (!empty($name) && !empty($password)) {
        $params = array(
            "name" => $name,
            "password" => $password,
            "service" => $service,
            "profile" => routeros_profile_id($API, 'ppp', $profile),
            "disabled" => $disabled,
        );

        if (!empty($local_addr)) $params["local-address"] = $local_addr;
        if (!empty($remote_addr)) $params["remote-address"] = $remote_addr;
        if (!empty($comment)) $params["comment"] = $comment;

        $API->comm("/ppp/secret/add", $params);

        echo "<script>window.location='./?ppp=secrets&session=" . $session . "'</script>";
        exit();
    } else {
        $error_msg = "Nama dan Password wajib diisi!";
    }
}
?>

<div class="row">
  <div class="col-8">
    <div class="card">
      <div class="card-header">
        <h3><i class="fa fa-user-plus"></i> Tambah Secret / User PPPoE</h3>
      </div>
      <div class="card-body">
        <?php if (!empty($error_msg)): ?>
          <div class="alert alert-danger"><?= $error_msg; ?></div>
        <?php endif; ?>

        <form autocomplete="off" method="post" action="">
          <table class="table">
            <tr>
              <td style="width: 180px;">Username</td>
              <td><input type="text" class="form-control" name="name" required placeholder="User PPPoE"></td>
            </tr>
            <tr>
              <td>Password</td>
              <td><input type="text" class="form-control" name="pass" required placeholder="Password"></td>
            </tr>
            <tr>
              <td>Service</td>
              <td>
                <select class="form-control" name="service">
                  <option value="pppoe" selected>pppoe</option>
                  <option value="any">any</option>
                  <option value="l2tp">l2tp</option>
                  <option value="pptp">pptp</option>
                  <option value="ovpn">ovpn</option>
                </select>
              </td>
            </tr>
            <tr>
              <td>Profile</td>
              <td>
                <select class="form-control" name="profile" id="ppp_profile" onchange="updateCommentPreview()">
                  <?php foreach ($getprofile as $p): ?>
                    <option value="<?= htmlspecialchars($p['name']); ?>"><?= htmlspecialchars($p['name']); ?></option>
                  <?php endforeach; ?>
                </select>
              </td>
            </tr>
            <tr>
              <td>Local Address (Opsional)</td>
              <td><input type="text" class="form-control" name="local_address" placeholder="10.10.10.1 (atau biarkan kosong)"></td>
            </tr>
            <tr>
              <td>Remote Address / Static IP (Opsional)</td>
              <td><input type="text" class="form-control" name="remote_address" placeholder="10.10.10.100 (atau biarkan kosong)"></td>
            </tr>
            <tr>
              <td>Comment</td>
              <td>
                <input type="text" class="form-control" name="comment" id="ppp_comment" placeholder="Keterangan / Alamat Pelanggan (misal: Ummi)">
                <small class="text-muted"><i class="fa fa-info-circle"></i> Cukup isi nama/alamat (contoh: <code>Ummi</code>), format <code>pkg:</code> dan <code>lunas:YYYY-MM</code> otomatis dibuatkan.</small>
              </td>
            </tr>
            <tr>
              <td>Status Disabled</td>
              <td>
                <select class="form-control" name="disabled">
                  <option value="no" selected>No (Aktif)</option>
                  <option value="yes">Yes (Nonaktif)</option>
                </select>
              </td>
            </tr>
            <tr>
              <td></td>
              <td>
                <button type="submit" name="save" class="btn bg-primary"><i class="fa fa-save"></i> Simpan Secret</button>
                <a href="./?ppp=secrets&session=<?= $session; ?>" class="btn bg-secondary">Batal</a>
              </td>
            </tr>
          </table>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
function updateCommentPreview() {
    var profSel = document.getElementById('ppp_profile');
    var commInput = document.getElementById('ppp_comment');
    if (!profSel || !commInput) return;
    var newProf = profSel.value;
    if (newProf.toLowerCase().indexOf('isolir') !== -1) return;
    
    var val = commInput.value;
    if (/pkg:[^|]+/.test(val)) {
        commInput.value = val.replace(/pkg:[^|]+/, 'pkg:' + newProf);
    }
}
</script>
