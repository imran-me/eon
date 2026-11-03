@once
<script>
(() => {
 const storageKey='cfpStageOpen';
 const bindFlow=()=>document.querySelectorAll('[data-file-stage-flow]').forEach(line=>{
  const button=line.closest('[data-file-stage]');
  let animation;
  const start=()=>{
   if(animation||button.disabled||window.matchMedia('(prefers-reduced-motion: reduce)').matches)return;
   animation=line.animate([{backgroundPositionX:'0px'},{backgroundPositionX:'12px'}],{duration:700,iterations:Infinity,easing:'linear'});
  };
  const stop=()=>{animation?.cancel();animation=null};
  button.addEventListener('mouseenter',start);button.addEventListener('mouseleave',stop);
  button.addEventListener('focus',start);button.addEventListener('blur',stop);
 });
 document.readyState==='loading'?document.addEventListener('DOMContentLoaded',bindFlow):bindFlow();
 // Defer restoration until all server-rendered rows exist.
 const restore=()=>{try{const id=sessionStorage.getItem(storageKey);const row=Array.from(document.querySelectorAll('[data-file-row]')).find(row=>row.dataset.fileRow===id);if(row?.nextElementSibling?.matches('[data-cfp-journey]')){row.nextElementSibling.hidden=false;row.setAttribute('aria-expanded','true')}sessionStorage.removeItem(storageKey)}catch(_){}};
 document.readyState==='loading'?document.addEventListener('DOMContentLoaded',restore):restore();
 let busy=false;
 document.addEventListener('click',async event=>{
  const button=event.target.closest('[data-file-stage]');
  if(!button||button.disabled||busy||typeof Swal==='undefined')return;
  const isCurrent=button.dataset.current==='1';busy=true;
  try {
   const result=await Swal.fire({
    title:button.dataset.label,
    text:isCurrent?`${button.dataset.name} is already at this stage.`:`Set ${button.dataset.name} from ${button.dataset.from} to ${button.dataset.label}? Earlier stages will appear completed and later stages upcoming.`,
    icon:'info',showCancelButton:!isCurrent,
    confirmButtonText:isCurrent?'Close':'Mark as current stage',confirmButtonColor:'#4f46e5',
    showLoaderOnConfirm:!isCurrent,allowOutsideClick:()=>!Swal.isLoading(),
    preConfirm:isCurrent?undefined:async()=>{
     try {
      const response=await fetch(@json(route('role.contract-files.stage',['role'=>$role,'contractFile'=>'__FILE__'])).replace('__FILE__',encodeURIComponent(button.dataset.fileId)),{
       method:'PATCH',credentials:'same-origin',
       headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]')?.content||''},
       body:JSON.stringify({stage:button.dataset.fileStage})
      });
      const data=await response.json().catch(()=>({}));
      if(!response.ok||!data.success)throw new Error(data.message||'Could not save the stage. Please try again.');
      return true;
     } catch(error){Swal.showValidationMessage(error.message);return false}
    }
   });
   if(result.isConfirmed&&!isCurrent){try{sessionStorage.setItem(storageKey,button.dataset.fileId)}catch(_){}location.reload()}
  } finally {busy=false}
 });
})();
</script>
@endonce
