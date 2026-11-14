<?php
/*
 * PPP Profiles Management for Mikhmon v3
 * Styled 1:1 with Mikhmon Hotspot User Profiles UI
 */
session_start();
error_reporting(0);

if (!isset($_SESSION["mikhmon"])) {
    header("Location:./admin.php?id=login");
    exit();
}

$session = $_GET['session'] ?? '';
$getprofile = $API->comm("/ppp/profile/print");
$TotalReg = count($getprofile);
?>

<div class="row">
<div class="col-12">
<div class="card">
<div class="card-header align-middle">
    <h3><i class="fa fa-pie-chart"></i> <?= $_ppp_profiles ?? "PPP Profiles" ?>
      <span style="font-size: 14px">
        <?php if ($TotalReg < 2) {
            echo "{$TotalReg} item";
        } else {
            echo "{$TotalReg} items";
        } ?>
        &nbsp; | &nbsp; <a href="./?ppp=add-profile&session=<?= $session; ?>" title="Tambah Profile"><i class="fa fa-plus-square"></i> <?= $_add ?? "Tambah" ?></a>
        &nbsp; | &nbsp; <a href="./?ppp=secrets&session=<?= $session; ?>" title="Secret List"><i class="fa fa-users"></i> <?= $_ppp_secrets ?? "User Secrets" ?></a>
      </span>
      <small id="loader" style="display: none;"><i><i class='fa fa-circle-o-notch fa-spin'></i> <?= $_processing ?? "Processing..." ?></i></small>
    </h3>
</div>

<div class="card-body">
  <div class="row">
    <div class="col-6 pd-t-5 pd-b-5">
      <div class="input-group">
        <div class="input-group-8 col-box-8">
          <input id="filterTable" type="text" style="padding:5.8px;" class="group-item group-item-l" placeholder="<?= $_search ?? "Cari profile..." ?>">
        </div>
      </div>
    </div>
    <div class="col-6 text-right pd-t-5 pd-b-5">
      <span class="badge bg-secondary pd-5 radius-3"><i class="fa fa-pie-chart"></i> Total: <?= $TotalReg; ?></span>
    </div>
  </div>

  <div class="overflow mr-t-10 box-bordered" style="max-height: 75vh">
  <table id="dataTable" class="table table-bordered table-hover text-nowrap">
    <thead>
      <tr>
        <th style="min-width: 50px;" class="text-center align-middle" id="cuser"><?= $TotalReg; ?></th>
        <th class="pointer" title="Click to sort"><i class="fa fa-sort"></i> <?= $_name ?? "Profile Name" ?></th>
        <th class="pointer" title="Click to sort"><i class="fa fa-sort"></i> Local Address</th>
        <th class="pointer" title="Click to sort"><i class="fa fa-sort"></i> Remote Address</th>
        <th class="pointer" title="Click to sort"><i class="fa fa-sort"></i> Rate Limit (Rx/Tx)</th>
        <th class="pointer" title="Click to sort"><i class="fa fa-sort"></i> Only One</th>
        <th class="pointer" title="Click to sort"><i class="fa fa-sort"></i> DNS Server</th>
        <th class="pointer" title="Click to sort"><i class="fa fa-sort"></i> <?= $_comment ?? "Comment" ?></th>
      </tr>
    </thead>
    <tbody id="tbody">
      <?php
      for ($i = 0; $i < $TotalReg; $i++) {
          $prof = $getprofile[$i];
          $id = $prof['.id'];
          $name = $prof['name'];
          $local_addr = $prof['local-address'] ?? '-';
          $remote_addr = $prof['remote-address'] ?? '-';
          $rate_limit = $prof['rate-limit'] ?? '-';
          $only_one = $prof['only-one'] ?? 'default';
          $dns_server = $prof['dns-server'] ?? '-';
          $comment = $prof['comment'] ?? '';

          $edit_url = "./?ppp=edit-profile&name=" . urlencode($name) . "&session=" . $session;
          $uriprocess = "'./?remove-pprofile=" . urlencode($id) . "&session=" . $session . "'";

          echo "<tr>";
          echo "<td style='text-align:center;'>";
          if ($name !== 'default' && $name !== 'default-encryption') {
              echo "<i class='fa fa-minus-square text-danger pointer' onclick=\"if(confirm('Hapus profile (" . addslashes($name) . ")?')){loadpage(" . $uriprocess . ");loader();}else{}\" title='Hapus " . htmlspecialchars($name) . "'></i>";
          } else {
              echo "<i class='fa fa-minus-square text-muted' style='opacity:0.3;' title='Default Profile'></i>";
          }
          echo "</td>";

          echo "<td><a title='Open Profile " . htmlspecialchars($name) . "' href='{$edit_url}'><i class='fa fa-edit'></i> " . htmlspecialchars($name) . "</a></td>";
          echo "<td>" . htmlspecialchars($local_addr) . "</td>";
          echo "<td>" . htmlspecialchars($remote_addr) . "</td>";
          echo "<td>" . htmlspecialchars($rate_limit) . "</td>";
          echo "<td>" . htmlspecialchars($only_one) . "</td>";
          echo "<td>" . htmlspecialchars($dns_server) . "</td>";
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
