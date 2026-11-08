<li class="approval-notifications" id="approval-notifications" data-url="<?php echo html_escape(site_url('main/approval_notifications')); ?>">
	<button type="button" class="approval-toggle" aria-label="Notifikasi persetujuan" aria-expanded="false" aria-controls="approval-panel">
		<i class="fa fa-bell-o" aria-hidden="true"></i><span class="approval-count" hidden></span>
	</button>
	<div class="approval-panel" id="approval-panel" hidden>
		<div class="approval-heading"><strong>Perlu persetujuan</strong><button type="button" class="approval-refresh" aria-label="Muat ulang notifikasi"><i class="fa fa-refresh" aria-hidden="true"></i></button></div>
		<div class="approval-content" aria-live="polite">Memuat notifikasi...</div>
		<div class="approval-pages" hidden><button type="button" class="approval-prev">Sebelumnya</button><span class="approval-page-number"></span><button type="button" class="approval-next">Berikutnya</button></div>
	</div>
</li>
