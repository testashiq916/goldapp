<style>
body{font-family:"Segoe UI",Tahoma,sans-serif;margin:0;background:#f3f6fb;color:#1f2937;}
.wrap{max-width:1550px;margin:12px auto;background:#fff;border:1px solid #d7dfeb;border-radius:10px;padding:14px;}
h1{margin:0 0 10px;font-size:20px;color:#173b63;}
.toolbar{display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end;margin-bottom:10px;}
.field{display:flex;flex-direction:column;gap:4px;min-width:140px;}
.field.wide{min-width:220px;flex:1;}
label{font-size:11px;font-weight:700;color:#375b84;}
input,select,button{height:32px;border:1px solid #bfd0e6;border-radius:6px;padding:0 8px;font-size:12px;box-sizing:border-box;}
button{cursor:pointer;background:#e8f2ff;border-color:#2a6398;color:#17456e;font-weight:700;}
button.primary{background:#e6f8ec;border-color:#2a7a42;color:#1b5b31;}
.pills{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px;}
.pill{border:1px solid #cad7e6;background:#f8fbff;border-radius:999px;padding:4px 12px;font-size:12px;}
.pill.red{border-color:#e8a0a0;background:#fff5f5;color:#a72c2c;font-weight:700;}
.pill.green{border-color:#a0d8a0;background:#f5fff5;color:#1b5b31;font-weight:700;}
.table-wrap{border:1px solid #d8e2ef;border-radius:8px;overflow:auto;max-height:68vh;}
table{width:100%;border-collapse:collapse;font-size:12px;}
th,td{border-bottom:1px solid #e5ecf5;padding:6px 8px;vertical-align:top;}
th{position:sticky;top:0;background:#edf4fc;text-align:left;z-index:1;}
td.num,th.num{text-align:right;white-space:nowrap;}
td.bal{color:#a72c2c;font-weight:700;}
tr.overdue td{background:#fff8f8;}
tr.tfoot td{background:#f0f6ff;font-weight:700;}
.empty{padding:24px;text-align:center;color:#64748b;}
@media print{.toolbar{display:none;}body{background:#fff;}.wrap{max-width:none;margin:0;border:0;}.table-wrap{max-height:none;overflow:visible;}th{position:static;}}

@media(max-width:900px){
  .wrap{margin:8px;padding:10px;}
  .toolbar{flex-wrap:wrap;gap:6px;}
  .field{min-width:120px;}
}
@media(max-width:640px){
  .wrap{margin:4px;padding:8px;border-radius:8px;}
  .toolbar{flex-direction:column;align-items:stretch;}
  .toolbar input,.toolbar select{width:100%;}
  .field,.field.wide{min-width:100%;flex:1 1 100%;}
  h1{font-size:17px;}
  .pills{flex-wrap:wrap;}
  .table-wrap{max-height:none;}
  th,td{padding:5px 6px;font-size:11px;}
}
@media(max-width:400px){
  .toolbar button{width:100%;}
}
</style>
