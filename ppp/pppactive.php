<?php
/*
 * Active PPPoE / PPP Connections for Mikhmon v3
 * Styled 1:1 with Mikhmon Hotspot Active UI
 */
session_start();
error_reporting(0);

if (!isset($_SESSION["mikhmon"])) {
    header("Location:./admin.php?id=login");
    exit();
}

$session = $_GET['session'] ?? '';
$getactive = $API->comm("/ppp/active/print");
$TotalReg = count($getactive);
?>

<div class="row">
<div class="col-12">
<div class="card">
<div class="card-header align-middle">
    <h3><i class="fa fa-plug"></i> <?= $_ppp_active ?? "PPPoE Active" ?>
      <span style="font-size: 14px">
        <?php if ($TotalReg < 2) {
            echo "{$TotalReg} item";
        } else {
            echo "{$TotalReg} items";
        } ?>
        &nbsp; | &nbsp; <a href="./?ppp=secrets&session=<?= $session; ?>" title="Secret List"><i class="fa fa-users"></i> <?= $_ppp_secrets ?? "User Secrets" ?></a>
        &nbsp; | &nbsp; <a href="./?ppp=active&session=<?= $session; ?>" title="Reload"><i class="fa fa-refresh"></i> Refresh</a>
      </span>
      <small id="loader" style="display: none;"><i><i class='fa fa-circle-o-notch fa-spin'></i> <?= $_processing ?? "Processing..." ?></i></small>
    </h3>
</div>

<div class="card-body">
  <div class="row">
    <div class="col-6 pd-t-5 pd-b-5">
      <div class="input-group">
        <div class="input-group-8 col-box-8">
          <input id="filterTable" type="text" style="padding:5.8px;" class="group-item group-item-l" placeholder="<?= $_search ?? "Cari user aktif..." ?>">
        </div>
        <div class="input-group-4 col-box-4">
          <button class="group-item group-item-r" onclick="location.reload(); loader();" title="Refresh"><i class="fa fa-refresh"></i> Refresh</button>
        </div>
      </div>
    </div>
    <div class="col-6 text-right pd-t-5 pd-b-5">
      <span class="badge bg-success pd-5 radius-3"><i class="fa fa-circle"></i> Online: <?= $TotalReg; ?></span>
    </div>
  </div>

  <div class="overflow mr-t-10 box-bordered" style="max-height: 75vh">
  <table id="dataTable" class="table table-bordered table-hover text-nowrap">
    <thead>
      <tr>
        <th style="min-width: 50px;" class="text-center align-middle" id="cuser"><?= $TotalReg; ?></th>
        <th class="pointer" title="Click to sort"><i class="fa fa-sort"></i> Service</th>
        <th class="pointer" title="Click to sort"><i class="fa fa-sort"></i> <?= $_name ?? "User" ?></th>
        <th class="pointer" title="Click to sort"><i class="fa fa-sort"></i> Address</th>
        <th class="pointer" title="Click to sort"><i class="fa fa-sort"></i> Caller ID (MAC)</th>
        <th class="text-right pointer" title="Click to sort"><i class="fa fa-sort"></i> Uptime</th>
        <th class="pointer" title="Click to sort"><i class="fa fa-sort"></i> Encoding</th>
        <th class="pointer" title="Click to sort"><i class="fa fa-sort"></i> <?= $_comment ?? "Comment" ?></th>
      </tr>
    </thead>
    <tbody id="tbody">
      <?php
      for ($i = 0; $i < $TotalReg; $i++) {
          $act = $getactive[$i];
          $id = $act['.id'];
          $user = $act['name'];
          $service = $act['service'] ?? 'pppoe';
          $caller = $act['caller-id'] ?? '-';
          $address = $act['address'] ?? '-';
          $uptime = formatDTM($act['uptime']);
          $encoding = $act['encoding'] ?? '-';
          $comment = $act['comment'] ?? '';

          $uriprocess = "'./?remove-pactive=" . urlencode($id) . "&session=" . $session . "'";

          echo "<tr>";
          echo "<td style='text-align:center;'>";
          echo "<span class='pointer' title='Putuskan Sesi " . htmlspecialchars($user) . "' onclick=\"if(confirm('Putuskan koneksi (" . addslashes($user) . ")?')){loadpage(" . $uriprocess . ");loader();}else{}\"><i class='fa fa-minus-square text-danger'></i></span>";
          echo "</td>";

          echo "<td><span class='badge bg-info'>" . htmlspecialchars($service) . "</span></td>";
          echo "<td><a title='Open Secret " . htmlspecialchars($user) . "' href='./?secret=" . urlencode($user) . "&session=" . $session . "'><i class='fa fa-edit'></i> " . htmlspecialchars($user) . "</a></td>";
          echo "<td><strong>" . htmlspecialchars($address) . "</strong></td>";
          echo "<td><code>" . htmlspecialchars($caller) . "</code></td>";
          echo "<td class='text-right'>" . htmlspecialchars($uptime) . "</td>";
          echo "<td>" . htmlspecialchars($encoding) . "</td>";
          echo "<td>" . htmlspecialchars($comment) . "</td>";
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
