<style id="cf-record-sheet-styles">@include('contract-flights.partials.passenger-sheet-styles')</style>
<script>
(()=>{
 const detailsTemplate=@json(route('role.contract-files.show',['role'=>$role,'contractFile'=>'__FILE_ID__']));
 const detailsUrl=id=>detailsTemplate.replace('__FILE_ID__',encodeURIComponent(id));
 let overlay,previous,controller;
 const close=()=>{controller?.abort();overlay?.remove();overlay=null;document.body.classList.remove('fdl-printing');previous?.focus()};
 window.openFileSheet=async(id,autoPrint=false)=>{
  close();previous=document.activeElement;controller=new AbortController();const active=controller;
  overlay=document.createElement('div');overlay.className='fdl-sheet-overlay';overlay.setAttribute('role','dialog');overlay.setAttribute('aria-modal','true');overlay.setAttribute('aria-label','File applicant record');
  overlay.innerHTML='<div class="fdl-sheet-bar"><strong>File record</strong><button type="button" class="fdl-sheet-btn" data-sheet-edit>Edit</button><a class="fdl-sheet-btn" data-sheet-full>Full details</a><button type="button" class="fdl-sheet-btn" data-sheet-print disabled>Print</button><button type="button" class="fdl-sheet-btn" data-sheet-close>× Close</button></div><div class="fdl-sheet is-flow" data-sheet-content>Loading file record…</div>';
  const current=overlay;document.body.append(current);
  current.querySelector('[data-sheet-full]').href=detailsUrl(id);
  current.querySelector('[data-sheet-close]').onclick=close;
  const edit=current.querySelector('[data-sheet-edit]');edit.hidden=typeof editFile!=='function'||!@json(auth()->user()->can('edit contract file'));edit.onclick=()=>{close();editFile(Number(id))};
  current.querySelector('[data-sheet-print]').onclick=()=>{document.body.classList.add('fdl-printing');window.print();document.body.classList.remove('fdl-printing')};
  current.addEventListener('keydown',event=>{if(event.key==='Escape'){close();return}if(event.key==='Tab'){const items=[...current.querySelectorAll('button:not([disabled]),a[href]')].filter(el=>!el.hidden);const first=items[0],last=items.at(-1);if(event.shiftKey&&document.activeElement===first){event.preventDefault();last.focus()}else if(!event.shiftKey&&document.activeElement===last){event.preventDefault();first.focus()}}});
  current.querySelector('[data-sheet-close]').focus();
  try{const response=await fetch(detailsUrl(id)+'?sheet=1',{headers:{'X-Requested-With':'XMLHttpRequest'},signal:active.signal});if(!response.ok||response.redirected)throw Error(response.redirected?'Session expired. Reload and sign in again.':'Unable to load file record (HTTP '+response.status+'). Close and retry.');const html=await response.text();if(overlay!==current)return;current.querySelector('[data-sheet-content]').innerHTML=html;current.querySelector('[data-sheet-print]').disabled=false;if(autoPrint){await document.fonts.ready;if(overlay===current)requestAnimationFrame(()=>{if(overlay===current)current.querySelector('[data-sheet-print]').click()})}}
  catch(error){if(error.name!=='AbortError'&&overlay===current)current.querySelector('[data-sheet-content]').textContent=error.message}
 };
 document.addEventListener('click',event=>{const link=event.target.closest('a[data-file-sheet]');if(!link||event.ctrlKey||event.metaKey||event.shiftKey||event.altKey)return;event.preventDefault();openFileSheet(link.dataset.fileSheet)});
 document.addEventListener('click',event=>{const button=event.target.closest('[data-file-print]');if(!button)return;event.preventDefault();openFileSheet(button.dataset.filePrint,true)},true);
})();
</script>
