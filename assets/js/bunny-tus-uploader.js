(function(){
  function log(msg){
    var el = document.getElementById('tus-log');
    if(!el) return; el.textContent += (typeof msg === 'string' ? msg : JSON.stringify(msg)) + "\n";
  }
  function $(sel){ return document.querySelector(sel); }
  document.addEventListener('DOMContentLoaded', function(){
    var btn = document.getElementById('tus-start');
    if(!btn) return;
    btn.addEventListener('click', async function(){
      var file = document.getElementById('tus-file').files[0];
      var title= document.getElementById('tus-title').value || file && file.name || 'WP Upload';
      if(!file){ alert('Bitte Datei wählen.'); return; }

      try {
        // 1) Create Video via REST
        var cv = await fetch(GodamBunnyTus.restUrl + 'create-video', {
          method: 'POST',
          headers: { 'Content-Type':'application/json', 'X-WP-Nonce': GodamBunnyTus.nonce },
          body: JSON.stringify({ title: title })
        }).then(r=>r.json());
        if(cv && cv.data && cv.data.status){ throw new Error(cv.message || 'Create video failed'); }
        var videoId = (cv && cv.videoId) || (cv && cv.raw && (cv.raw.guid || cv.raw.videoId));
        if(!videoId) throw new Error('Keine videoId erhalten');
        log(['CreateVideo OK', cv]);

        // 2) Presign
        var expIn = 3600;
        var ps = await fetch(GodamBunnyTus.restUrl + 'presign?videoId=' + encodeURIComponent(videoId) + '&expiresIn=' + expIn, {
          headers: { 'X-WP-Nonce': GodamBunnyTus.nonce }
        }).then(r=>r.json());
        if(ps && ps.data && ps.data.status){ throw new Error(ps.message || 'Presign failed'); }
        log(['Presign OK', ps]);

        // 3) TUS Upload (client -> Bunny)
        var upload = new tus.Upload(file, {
          endpoint: 'https://video.bunnycdn.com/tusupload', // Bunny TUS endpoint
          retryDelays: [0, 3000, 5000, 10000, 20000, 60000],
          headers: {
            AuthorizationSignature: ps.authorizationSignature,
            AuthorizationExpire: ps.authorizationExpire,
            LibraryId: GodamBunnyTus.libraryId,
            VideoId: videoId
          },
          metadata: {
            filetype: file.type || 'video/mp4',
            title: title
          },
          onError: function (error) {
            log(['UploadError', error && (error.message||error.toString())]);
            document.getElementById('tus-status').textContent = 'Fehler: ' + (error.message||error);
          },
          onProgress: function (bytesUploaded, bytesTotal) {
            var pct = Math.floor(bytesUploaded / bytesTotal * 100);
            var p = document.getElementById('tus-progress');
            p.value = pct; document.getElementById('tus-status').textContent = pct + '%';
          },
          onSuccess: function () {
            document.getElementById('tus-status').textContent = 'Fertig';
            log(['UploadDone', upload.url]);
          }
        });

        // Resume if possible
        upload.findPreviousUploads().then(function(previousUploads){
          if (previousUploads.length) upload.resumeFromPreviousUpload(previousUploads[0]);
          upload.start();
        });
      } catch(e){
        log(e && (e.message||e.toString()));
        alert(e && e.message ? e.message : e);
      }
    });
  });
})();
