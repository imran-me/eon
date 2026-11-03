<script>
(() => {
 const root=document.getElementById('cfac-client');
 const agents=root.querySelectorAll('[data-cfac-agent]');
 const files=root.querySelectorAll('[data-cfac-due-agent]');
 const clear=root.querySelector('#cfac-due-clear');
 let selected=null;
 let selectedName='',scope='due';
 function pick(key,name,scroll=true) {
  selected=key;selectedName=name; let count=0,charge=0,paid=0,due=0;
  files.forEach(row=>{
   const match=(key===null || row.dataset.cfacDueAgent===key)&&(scope==='all'||Number(row.dataset.due)>0);
   row.hidden=!match;
   if(match){count++;charge+=Number(row.dataset.charge);paid+=Number(row.dataset.paid);due+=Number(row.dataset.due)}
  });
  agents.forEach(row=>{const on=row.dataset.cfacAgent===key;row.classList.toggle('is-on',on);row.setAttribute('aria-pressed',String(on))});
  clear.hidden=key===null;
  root.querySelector('#cfac-due-empty').hidden=count>0;
  root.querySelector('#cfac-file-title').textContent=scope==='due'?'Due files / applicants':'All files / applicants';
  root.querySelector('#cfac-due-empty').textContent=scope==='due'?'No outstanding files for this selection.':'No files for this selection.';
  root.querySelector('#cfac-due-hint').textContent=`${key===null?'All agents / clients':name} — ${count} file${count===1?'':'s'}${scope==='due'?' with an unpaid client charge':' including paid and closed files'} · within the selected account filters`;
  root.querySelector('#cfac-due-total-label').textContent=key===null ? 'Total for all agents / clients' : `Total for ${name}`;
  const money=v=>(v<0?'−':'')+'৳'+Math.round(Math.abs(v)).toLocaleString('en-US');
  root.querySelector('#cfac-due-charge').textContent=money(charge);root.querySelector('#cfac-due-paid').textContent=money(paid);root.querySelector('#cfac-due-total').textContent=money(due);
  if(scroll) root.querySelector('#cfac-due-files').scrollIntoView({behavior:matchMedia('(prefers-reduced-motion: reduce)').matches?'auto':'smooth',block:'start'});
 }
 agents.forEach(row=>{
  const select=()=>pick(selected===row.dataset.cfacAgent ? null : row.dataset.cfacAgent,row.dataset.cfacAgentName);
  row.addEventListener('click',event=>{if(event.target.closest('a'))return;select()});
  row.addEventListener('keydown',event=>{if(event.target!==row || !['Enter',' '].includes(event.key))return;event.preventDefault();select()});
 });
 root.querySelectorAll('[data-cfac-scope]').forEach(button=>button.addEventListener('click',()=>{
  scope=button.dataset.cfacScope;
  root.querySelectorAll('[data-cfac-scope]').forEach(item=>{item.setAttribute('aria-pressed',String(item===button));item.classList.toggle('is-on',item===button)});
  pick(selected,selectedName,false);
 }));
 clear.addEventListener('click',()=>{pick(null,'',false);agents[0]?.focus({preventScroll:true})});
})();
</script>
