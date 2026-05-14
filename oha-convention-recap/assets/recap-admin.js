(function () {
    function makeStatRow() {
        var row = document.createElement('div');
        row.className = 'oha-recap-row';
        row.style.cssText = 'display:flex;gap:12px;align-items:center;margin-bottom:10px;';
        row.innerHTML =
            '<input type="text" name="oha_recap_stat_value[]" value="" placeholder="e.g. 847" style="width:140px;">' +
            '<input type="text" name="oha_recap_stat_label[]" value="" placeholder="e.g. Total Attendees" style="width:260px;">' +
            '<button type="button" class="button oha-recap-remove-row">Remove</button>';
        return row;
    }

    function makeVideoRow() {
        var row = document.createElement('div');
        row.className = 'oha-recap-row';
        row.style.cssText = 'display:flex;gap:12px;align-items:center;margin-bottom:10px;flex-wrap:wrap;';
        row.innerHTML =
            '<input type="text" name="oha_recap_vid_title[]" value="" placeholder="Video title (optional)" style="width:200px;">' +
            '<input type="text" name="oha_recap_vid_url[]"   value="" placeholder="Video URL or Facebook URL" style="width:420px;">' +
            '<button type="button" class="button oha-recap-remove-row">Remove</button>';
        return row;
    }

    document.addEventListener('DOMContentLoaded', function () {
        var addStat = document.getElementById('oha-recap-add-stat');
        if (addStat) {
            addStat.addEventListener('click', function () {
                document.getElementById('oha-recap-stats-list').appendChild(makeStatRow());
            });
        }

        var addVideo = document.getElementById('oha-recap-add-video');
        if (addVideo) {
            addVideo.addEventListener('click', function () {
                document.getElementById('oha-recap-videos-list').appendChild(makeVideoRow());
            });
        }

        document.addEventListener('click', function (e) {
            if (e.target && e.target.classList.contains('oha-recap-remove-row')) {
                e.target.closest('.oha-recap-row').remove();
            }
        });
    });
})();
