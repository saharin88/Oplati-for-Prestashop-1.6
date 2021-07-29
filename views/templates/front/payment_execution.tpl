<div id="oplati" style="text-align: center;" data-timeout="{$check_status_timeout * 1000}">
	<div class="page-title">
		<h3>{l s='Scan the QR code to pay' mod='oplati'}</h3>
	</div>
	<p>
		<img src="https://chart.googleapis.com/chart?cht=qr&chl={$dynamicQR}&chs={$qrsize}x{$qrsize}&choe=UTF-8&chld=L|2" alt="{l s='Oplati' mod='oplati'}" style="display: inline;">
	</p>
	<p>
		<a class="btn btn-default" href="https://getapp.o-plati.by/map/?app_link={$dynamicQR}" target="_blank">{l s='Oplati' mod='oplati'}</a>
	</p>
</div>
