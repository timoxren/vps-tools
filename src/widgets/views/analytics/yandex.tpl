<script type="text/javascript">
	(function (d, w, c) {
		(w[ c ] = w[ c ] || []).push(function () {
			try {
				w.yaCounter{$key} = new Ya.Metrika2({
					id                  :{$key},
					clickmap            : true,
					trackLinks          : true,
					accurateTrackBounce : true,
					webvisor            : true,
					{if !empty($hash)}
					userParams          : { UserID : '{$hash}' }
					{/if}
				});
			} catch (e) { }
		});
		var n   = d.getElementsByTagName("script")[ 0 ],
		    s   = d.createElement("script"),
		    f   = function () { n.parentNode.insertBefore(s, n); };
		s.type  = "text/javascript";
		s.async = true;
		s.src   = "https://mc.yandex.ru/metrika/tag.js";
		if (w.opera == "[object Opera]") {
			d.addEventListener("DOMContentLoaded", f, false);
		} else { f(); }
	})(document, window, "yandex_metrika_callbacks2");
</script>