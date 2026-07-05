<?php $csrf = $_SESSION['csrf_token'] ?? ''; ?>
<style>
#sh-wrap{background:#0d1117;height:100%;display:flex;flex-direction:column;font-family:monospace;font-size:16px}
#sh-out{flex:1;overflow-y:auto;padding:10px;color:#c9d1d9;white-space:pre-wrap;font-size:15px;line-height:1.5}
#sh-inp{display:flex;padding:8px 10px;background:#161b22;border-top:1px solid #30363d;align-items:center}
#sh-prompt{color:#3fb950;white-space:pre;margin-right:4px}
#sh-cmd{flex:1;background:transparent;border:none;outline:none;color:#c9d1d9;font:inherit}
</style>
<div class="sec" style="padding:0;overflow:hidden;height:calc(100vh - 140px);min-height:300px">
<div id="sh-wrap">
<div id="sh-out"></div>
<div id="sh-inp">
<span id="sh-prompt">$ </span>
<input id="sh-cmd" type="text" autocomplete="off" spellcheck="false" placeholder="command">
</div>
</div></div>
<script>
(function(){
var CSRF='<?= htmlspecialchars($csrf) ?>';
var out=document.getElementById('sh-out');
var inp=document.getElementById('sh-cmd');
var promptEl=document.getElementById('sh-prompt');
var busy=false;

function addLine(text,cls){
 if(!text)return;
 var d=document.createElement('div');
 d.textContent=text;
 d.style.cssText='padding:1px 0'+(cls?';color:'+cls:'');
 out.appendChild(d);
 out.scrollTop=out.scrollHeight;
}

function submit(){
 var cmd=inp.value;
 if(!cmd.trim()){inp.value='';return;}
 busy=true;inp.disabled=true;
 addLine('$ '+cmd,'#8b949e');

 var x=new XMLHttpRequest();
 x.open('POST','panel/ssh_exec.php',true);
 x.setRequestHeader('Content-Type','application/x-www-form-urlencoded');
 x.responseType='json';
  x.onload=function(){
  if(x.status===200&&x.response){
   if(x.response.output)addLine(x.response.output);
   if(x.response.prompt)promptEl.textContent=x.response.prompt.replace(/\x1b\[[0-9;]*[a-zA-Z]/g,'');
  }else{
   addLine('Error: HTTP '+x.status,'#ff7b72');
  }
  inp.value='';inp.disabled=false;busy=false;inp.focus();
 };
 x.onerror=function(){addLine('Network error','#ff7b72');inp.value='';inp.disabled=false;busy=false;inp.focus();};
 x.send('cmd='+encodeURIComponent(cmd)+'&csrf_token='+encodeURIComponent(CSRF));
}

inp.addEventListener('keydown',function(e){
 if(e.key==='Enter'&&!busy){e.preventDefault();submit();}
 if(e.key==='c'&&(e.ctrlKey||e.metaKey)){busy=false;inp.disabled=false;inp.value='';}
});
inp.focus();
})();
</script>
