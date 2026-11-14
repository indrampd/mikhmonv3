<?php
/*
 * PPPoE / PPP Secrets Management for Mikhmon v3
 * Styled 1:1 with Mikhmon Hotspot Users UI
 */
session_start();
error_reporting(0);
ini_set('max_execution_time', 300);

if (!isset($_SESSION["mikhmon"])) {
    header("Location:./admin.php?id=login");
    exit();
}

$session = $_GET['session'] ?? '';
$prof = $_GET['profile'] ?? 'all';

// Load profiles
$getprofile = $API->comm("/ppp/profile/print");
$TotalReg2 = count($getprofile);

// Load all secrets count
$countall = $API->comm("/ppp/secret/print", array("count-only" => ""));
$TotalRegAll = is_numeric($countall) ? (int)$countall : 0;

// Load secrets with filter
if ($prof == "all" || empty($prof)) {
    $getuser = $API->comm("/ppp/secret/print");
} else {
    $getuser = $API->comm("/ppp/secret/print", array("?profile" => "$prof"));
}
$TotalReg = count($getuser);

// Active connections for online status mapping
$getactive = $API->comm("/ppp/active/print");
$TotalActive = count($getactive);
$active_map = array();
if (is_array($getactive)) {
    foreach ($getactive as $act) {
        if (!empty($act['name'])) {
            $active_map[$act['name']] = $act;
        }
    }
}
?>

<div class="row">
<div class="col-12">
<div class="card">
<div class="card-header">
    <h3><i class="fa fa-users"></i> <?= $_ppp_secrets ?? "PPPoE Secrets" ?>
      <span style="font-size: 14px">
        &nbsp; | &nbsp; <a href="./?ppp=addsecret&session=<?= $session; ?>" title="Tambah Secret"><i class="fa fa-user-plus"></i> <?= $_add ?? "Tambah" ?></a>
        &nbsp; | &nbsp; <a href="./?ppp=active&session=<?= $session; ?>" title="Active Connections"><i class="fa fa-exchange"></i> <?= $_ppp_active ?? "PPP Active" ?> (<?= $TotalActive; ?>)</a>
        &nbsp; | &nbsp; <a href="./?ppp=profiles&session=<?= $session; ?>" title="Profiles"><i class="fa fa-pie-chart"></i> <?= $_ppp_profiles ?? "Profiles" ?> (<?= $TotalReg2; ?>)</a>
      </span>
      <small id="loader" style="display: none;"><i><i class='fa fa-circle-o-notch fa-spin'></i> <?= $_processing ?? "Processing..." ?> </i></small>
    </h3>
</div>

<div class="card-body">
  <div class="row">
    <div class="col-6 pd-t-5 pd-b-5">
      <div class="input-group">
        <div class="input-group-6 col-box-6">
          <input id="filterTable" type="text" style="padding:5.8px;" class="group-item group-item-l" placeholder="<?= $_search ?? "Cari..." ?>">
        </div>
        <div class="input-group-6 col-box-6">
          <select style="padding:5px;" class="group-item group-item-r" onchange="location = this.value; loader()" title="Filter by Profile">
            <option value="./?ppp=secrets&profile=all&session=<?= $session; ?>"><?= $_show_all ?? "Semua Profile" ?> (<?= $TotalRegAll; ?>)</option>
            <?php
            for ($i = 0; $i < $TotalReg2; $i++) {
                $pname = $getprofile[$i]['name'];
                $selected = ($prof === $pname) ? 'selected' : '';
                echo "<option value='./?ppp=secrets&profile=" . urlencode($pname) . "&session=" . $session . "' {$selected}>" . htmlspecialchars($pname) . "</option>";
            }
            ?>
          </select>
        </div>
      </div>
    </div>
    <div class="col-6 text-right pd-t-5 pd-b-5">
      <span class="badge bg-success pd-5 radius-3"><i class="fa fa-circle"></i> Online: <?= $TotalActive; ?></span>
      <span class="badge bg-secondary pd-5 radius-3 mr-l-5"><i class="fa fa-users"></i> Total: <?= $TotalRegAll; ?></span>
    </div>
  </div>

  <div class="overflow mr-t-10 box-bordered" style="max-height: 75vh">
  <table id="dataTable" class="table table-bordered table-hover text-nowrap">
    <thead>
    <tr>
      <th style="min-width:65px;" class="align-middle text-center" id="cuser"><?= $TotalReg; ?></th>
      <th class="pointer" title="Click to sort"><i class="fa fa-sort"></i> <?= $_name ?? "Username" ?></th>
      <th>Password</th>
      <th class="pointer" title="Click to sort"><i class="fa fa-sort"></i> <?= $_profile ?? "Profile" ?></th>
      <th class="pointer" title="Click to sort"><i class="fa fa-sort"></i> Service</th>
      <th class="pointer" title="Click to sort"><i class="fa fa-sort"></i> Remote Address</th>
      <th class="pointer" title="Click to sort"><i class="fa fa-sort"></i> Caller ID</th>
      <th class="pointer" title="Click to sort"><i class="fa fa-sort"></i> Status</th>
      <th class="pointer" title="Click to sort"><i class="fa fa-sort"></i> <?= $_comment ?? "Comment" ?></th>
    </tr>
    </thead>
    <tbody id="tbody">
    <?php
    for ($i = 0; $i < $TotalReg; $i++) {
        $userdetails = $getuser[$i];
        $uid = $userdetails['.id'];
        $uname = $userdetails['name'];
        $upass = $userdetails['password'] ?? '';
        $uprofile = $userdetails['profile'] ?? 'default';
        $uservice = $userdetails['service'] ?? 'any';
        $uremote = $userdetails['remote-address'] ?? '-';
        $ucaller = $userdetails['caller-id'] ?? '-';
        $ucomment = $userdetails['comment'] ?? '';
        $udisabled = ($userdetails['disabled'] === 'true');
        $is_online = isset($active_map[$uname]);

        echo "<tr>";
        echo "<td style='text-align:center;'>";
        echo "<i class='fa fa-minus-square text-danger pointer' onclick=\"if(confirm('Yakin ingin menghapus user (" . addslashes($uname) . ")?')){loadpage('./?remove-pppsecret=" . urlencode($uid) . "&session=" . $session . "');loader();}else{}\" title='Hapus " . htmlspecialchars($uname) . "'></i>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";
        if ($udisabled) {
            $uriprocess = "'./?enable-pppsecret=" . urlencode($uid) . "&session=" . $session . "'";
            echo '<span class="text-warning pointer" title="Enable User ' . htmlspecialchars($uname) . '" onclick="loadpage(' . $uriprocess . ');loader();"><i class="fa fa-lock"></i></span>';
        } else {
            $uriprocess = "'./?disable-pppsecret=" . urlencode($uid) . "&session=" . $session . "'";
            echo '<span class="pointer text-green" title="Disable User ' . htmlspecialchars($uname) . '" onclick="loadpage(' . $uriprocess . ');loader();"><i class="fa fa-unlock"></i></span>';
        }
        echo "</td>";

        echo "<td><a title='Open User " . htmlspecialchars($uname) . "' href='./?secret=" . urlencode($uname) . "&session=" . $session . "'><i class='fa fa-edit'></i> " . htmlspecialchars($uname) . " </a></td>";
        echo "<td><code>" . htmlspecialchars($upass) . "</code></td>";
        echo "<td><a href='./?ppp=secrets&profile=" . urlencode($uprofile) . "&session=" . $session . "' title='Filter by " . htmlspecialchars($uprofile) . "'>" . htmlspecialchars($uprofile) . "</a></td>";
        echo "<td>" . htmlspecialchars($uservice) . "</td>";
        echo "<td>" . htmlspecialchars($uremote) . "</td>";
        echo "<td>" . htmlspecialchars($ucaller) . "</td>";

        echo "<td>";
        if ($is_online) {
            echo '<span class="text-green"><i class="fa fa-circle"></i> Online</span>';
        } elseif ($udisabled) {
            echo '<span class="text-danger"><i class="fa fa-ban"></i> Disabled</span>';
        } else {
            echo '<span class="text-muted" style="opacity:0.6;"><i class="fa fa-circle-o"></i> Offline</span>';
        }
        echo "</td>";

        echo "<td>" . htmlspecialchars($ucomment) . "</td>";
        echo "</tr>";
    }
    ?>
    </tbody>
  </table>
  </div>
</div>
</div>
</div>
</div>
