<?php
/*
 * Edit PPPoE / PPP Secret for Mikhmon v3
 */
session_start();
error_reporting(0);
require_once dirname(__DIR__) . '/lib/billing.class.php';

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
    $comment = MikhmonBilling::syncComment($comment, $profile, false);
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

    // If address is specified, update it. If explicitly emptied, use RouterOS /unset
    // to actually clear the value (sending empty string causes trap error, omitting keeps old value).
    $unset_keys = array();
    foreach (array("local-address" => $local_addr, "remote-address" => $remote_addr) as $key => $val) {
        if ($val !== "") {
            $params[$key] = $val;
        } else if (!empty($sec[$key])) {
            $unset_keys[] = $key;
        }
    }

    $res = $API->comm("/ppp/secret/set", $params);
    if (empty($res["!trap"])) {
        foreach ($unset_keys as $ukey) {
            $API->comm("/ppp/secret/unset", array(".id" => $sec['.id'], "value-name" => $ukey));
        }
    }
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
              <td class="align-middle">Password</td>
              <td>
                <div class="input-group">
                  <div class="input-group-11 col-box-10">
                    <input class="group-item group-item-l" id="passmk" type="password" name="pass" title="Password Secret" value="<?= htmlspecialchars($sec['password'] ?? ''); ?>" required="1"/>
                  </div>
                  <div class="input-group-1 col-box-2">
                    <div class="group-item group-item-r pd-2p5 text-center align-middle">
                      <input title="Show/Hide Password" type="checkbox" onclick="PassMk()">
                    </div>
                  </div>
                </div>
              </td>
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
                <select class="form-control" name="profile" id="ppp_profile" onchange="updateCommentPreview()">
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
              <td>
                <input type="text" class="form-control" name="comment" id="ppp_comment" value="<?= htmlspecialchars($sec['comment'] ?? ''); ?>">
                <small class="text-muted"><i class="fa fa-info-circle"></i> Catatan: Tag <code>pkg:</code> akan otomatis diselaraskan dengan Profile yang dipilih saat disimpan.</small>
              </td>
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

<script>
function updateCommentPreview() {
    var profSel = document.getElementById('ppp_profile');
    var commInput = document.getElementById('ppp_comment');
    if (!profSel || !commInput) return;
    var newProf = profSel.value;
    if (newProf.toLowerCase().indexOf('isolir') !== -1) return; // don't overwrite if isolir
    
    var val = commInput.value;
    if (/pkg:[^|]+/.test(val)) {
        commInput.value = val.replace(/pkg:[^|]+/, 'pkg:' + newProf);
    } else if (val.trim() !== '') {
        commInput.value = val.trim() + '|pkg:' + newProf;
    }
}
</script>

<script>
function PassMk(){
  var x = document.getElementById('passmk');
  if (x.type === 'password') {
    x.type = 'text';
  } else {
    x.type = 'password';
  }
}
</script>
