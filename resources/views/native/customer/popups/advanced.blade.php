<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $mode === 'S' ? 'Advanced Details of Suppliers' : 'Advanced Details of Customers' }}</title>
    <style>
        body { font-family: Arial, sans-serif; background: #c0c0c0; margin: 0; padding: 12px; }
        h2 { margin: 0 0 10px; font-size: 17px; color: #333; }
        .wrap { background: #fff; border: 1px solid #999; padding: 12px; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0 24px; }
        .col { display: flex; flex-direction: column; gap: 7px; }
        .row { display: flex; align-items: center; gap: 8px; }
        .row label { min-width: 210px; text-align: right; font-size: 12px; font-weight: 700; }
        .row input, .row select { font-size: 12px; border: 1px solid #aaa; padding: 4px 6px; }
        .row input[type="text"] { width: 220px; }
        .row input.wide { width: 320px; }
        .row input[type="date"] { width: 150px; }
        .row select { width: 170px; }
        .ttl { margin-top: 8px; font-size: 12px; font-weight: 700; text-decoration: underline; color: #333; }
        .btnbar { margin-top: 14px; text-align: center; }
        .btnbar button { border: 1px solid #888; background: #e6e6e6; padding: 7px 28px; font-size: 12px; font-weight: 700; cursor: pointer; }
    </style>
</head>
<body>
<div class="wrap">
    <h2>{{ $mode === 'S' ? 'Advanced Details of Suppliers' : 'Advanced Details of Customers' }}</h2>
    <form id="f">
        <div class="grid">
            <div class="col">
                <div class="row"><label>Pin :</label><input type="text" name="pincode"></div>
                <div class="row"><label>Fax :</label><input class="wide" type="text" name="fax"></div>
                <div class="row"><label>Period of relationship :</label>{!! selectHtml('relationperiod',['<1 Year','1-2 Year','2-4 Year','>4 Year']) !!}</div>
                <div class="row"><label>In which way related :</label>{!! selectHtml('relationway',['Mouth to Mouth','Channels','Newspaper','Hoardings','General Add','C/o']) !!}</div>
                <div class="row"><label>Name :</label><input class="wide" type="text" name="relationwayname"></div>
                <div class="row"><label>Based of relationship :</label>{!! selectHtml('relationbases',['Owner Family','Manager','PRO','Staff','Others']) !!}</div>
                <div class="row"><label>Name :</label><input class="wide" type="text" name="relationbasesname"></div>
                <div class="row"><label>Purchase Purpose :</label>{!! selectHtml('purchasepurpose',['Marriage','General','Engagement','Gift','Birth Day']) !!}</div>
                <div class="row"><label>Purchase Category :</label>{!! selectHtml('purchasecategory',['<1','1-5','5-10','10-25','25-50','50-100','>100']) !!}</div>
                <div class="row"><label>Which type item purchased :</label>{!! selectHtml('purchasetype',['Ordinary','Culcutta','Antic','Diamond']) !!}</div>
                <div class="row"><label>Working field :</label>{!! selectHtml('working',['Employee','Business','Gulf','Agriculture']) !!}</div>
                <div class="row"><label>Prompt updating order :</label>{!! selectHtml('promptorder',['Yes','No']) !!}</div>
                <div class="ttl">Date of Functions</div>
                <div class="row"><label>Marriage :</label><input type="date" name="dtmarriage"></div>
                <div class="row"><label>Engagement :</label><input type="date" name="dtengagement"></div>
                <div class="row"><label>Birthday :</label><input type="date" name="dtbirthday"></div>
            </div>
            <div class="col">
                <div class="row"><label>Customers Comments :</label><input class="wide" type="text" name="comment"></div>
                <div class="row"><label>Attitude of Staff :</label>{!! selectHtml('staffperf',['Excellent','Good','Average','Poor']) !!}</div>
                <div class="row"><label>Friendly Welcome from PRO :</label>{!! selectHtml('properf',['Excellent','Good','Average','Poor']) !!}</div>
                <div class="row"><label>Staff responsiveness :</label>{!! selectHtml('respperf',['Excellent','Good','Average','Poor']) !!}</div>
                <div class="row"><label>Speed of service :</label>{!! selectHtml('speedperf',['Excellent','Good','Average','Poor']) !!}</div>
                <div class="row"><label>Showroom cleanliness :</label>{!! selectHtml('cleanperf',['Excellent','Good','Average','Poor']) !!}</div>
                <div class="row"><label>Basic facilities :</label>{!! selectHtml('facilityperf',['Excellent','Good','Average','Poor']) !!}</div>
                <div class="row"><label>Parking facility :</label>{!! selectHtml('parkfacilityperf',['Excellent','Good','Average','Poor']) !!}</div>
                <div class="row"><label>Will continue to shop :</label>{!! selectHtml('contshop',['Yes','No']) !!}</div>
                <div class="row"><label>Will recommend :</label>{!! selectHtml('recommend',['Yes','No']) !!}</div>
                <div class="row"><label>Gold Rate Inform :</label>{!! selectHtml('grateinform',['Yes','No']) !!}</div>
            </div>
        </div>
        <div class="btnbar"><button type="submit">OK</button></div>
    </form>
</div>
@php
function selectHtml(string $name, array $opts): string {
    $h = '<select name="'.$name.'">';
    foreach ($opts as $v) $h .= '<option value="'.e($v).'">'.e($v).'</option>';
    return $h.'</select>';
}
@endphp
<script>
(() => {
    const form = document.getElementById('f');
    const mode = @json($mode);
    const fieldNames = [...form.elements].filter(e => e.name).map(e => e.name);
    const initDefaults = {
        relationperiod:'<1 Year', relationway:'Mouth to Mouth', relationbases:'Owner Family', purchasepurpose:'Marriage',
        purchasetype:'Ordinary', working:'Business', promptorder:'Yes', staffperf:'Good', properf:'Good',
        respperf:'Good', speedperf:'Good', cleanperf:'Good', facilityperf:'Good', parkfacilityperf:'Good',
        contshop:'Yes', recommend:'Yes', grateinform:'No'
    };
    function setData(d){
        const data = d || {};
        fieldNames.forEach((k) => {
            const el = form.elements[k];
            const v = data[k] !== undefined && data[k] !== null && data[k] !== '' ? data[k] : (initDefaults[k] || '');
            if (el) el.value = v;
        });
    }
    window.addEventListener('message', (ev) => {
        if (!ev.data || typeof ev.data !== 'object') return;
        if (ev.data.type === 'customer-advanced-data') setData(ev.data.data || {});
    });
    if (window.opener) {
        window.opener.postMessage({ type:'customer-advanced-request', mode }, '*');
    }
    form.addEventListener('submit', (e) => {
        e.preventDefault();
        const data = {};
        fieldNames.forEach(k => data[k] = form.elements[k].value || '');
        if (window.opener) {
            window.opener.postMessage({ type:'customer-advanced-save', data }, '*');
        }
        window.close();
    });
})();
</script>
</body>
</html>

