<script>
(() => {
 const modal=document.getElementById('cfbld-modal'),form=modal.querySelector('form'),cards=modal.querySelector('#cfbld-cards');
 const holders=@json($passportHoldersJson), categoryData=@json($categoriesJson), documents=@json($fileDocuments);
 const existing=@json($filesJson);
 const submitUrl=@json(route('role.contract-files.store',['role'=>$role])), csrf=@json(csrf_token());
 // Setup (2026-10-25): null until its migration ran and its routes exist — then the job category alone fills the card, as before.
 const setupUrl=@json($setupMatchUrl ?? null);
 let step=0,sequence=0,token='',saving=false,previousFocus=null;
 function enhanceSelects(root=modal){
  if(!window.jQuery?.fn.select2)return;
  root.querySelectorAll('select').forEach(select=>{
   const picker=jQuery(select);
   if(picker.hasClass('select2-hidden-accessible'))return;
   picker.select2({dropdownParent:jQuery(modal),width:'100%',minimumResultsForSearch:0});
   // Select2 emits a jQuery event; the builder's presets use native listeners.
   let bridging=false;
   picker.on('change.cfbld',event=>{
    if(event.originalEvent||bridging)return;
    bridging=true;select.dispatchEvent(new Event('change',{bubbles:true}));bridging=false;
   });
  });
 }
 function refreshSelects(root=modal){
  if(!window.jQuery?.fn.select2)return;
  root.querySelectorAll('select.select2-hidden-accessible').forEach(select=>jQuery(select).trigger('change.select2'));
 }
 const money=n=>'৳'+Number(n||0).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2});
 const field=(card,name)=>card.querySelector(`[data-field="${name}"]`);
 const value=(card,name)=>field(card,name)?.value || '';
 const label=(card,name)=>field(card,name)?.selectedOptions?.[0]?.textContent || value(card,name);
 function error(message){const el=modal.querySelector('#cfbld-error');el.textContent=message;el.hidden=!message;}
 function collapse(card,collapsed){const body=card.querySelector('[data-bld-card-body]'),button=card.querySelector('[data-bld-collapse]'),header=card.querySelector('header');body.hidden=collapsed;card.classList.toggle('is-collapsed',collapsed);button.setAttribute('aria-expanded',String(!collapsed));header.setAttribute('aria-expanded',String(!collapsed));const action=collapsed?'Expand applicant':'Collapse applicant';button.title=action;button.setAttribute('aria-label',action);button.querySelector('i').className='fas '+(collapsed?'fa-chevron-down':'fa-chevron-up');}
 function paintCardSummary(card,price,cost){
  const summary=card.querySelector('[data-bld-summary]');summary.textContent=`Cost ${money(cost)} · Profit ${money(price-cost)}`;summary.classList.toggle('loss',price<cost);
  const tags=card.querySelector('[data-bld-tags]');tags.replaceChildren();
  const holder=holders.find(h=>String(h.id)===value(card,'passport_holder_id'));
  const checklist=card.querySelectorAll('[data-bld-checklist] input'),received=card.querySelectorAll('[data-bld-checklist] input:checked').length;
  const details=[holder?.passport_no||'No passport',value(card,'contract_file_category_id')?label(card,'contract_file_category_id'):'No category',field(card,'training.enabled').checked?'Immigration training':'No training',`${received}/${checklist.length} documents`];
  details.forEach((text,index)=>{const tag=document.createElement('span');tag.textContent=text;if(index===3 && checklist.length>0 && received===checklist.length)tag.className='good';tags.append(tag)});
 }
 function paintMoney(card,price,processing,training,other){
  const cost=processing+training+other,profit=price-cost,bar=card.querySelector('[data-bld-money]');bar.replaceChildren();
  for(const [title,amount] of [['Cost',cost],['Charge',price],['Profit',profit]]){const item=document.createElement('span'),bold=document.createElement('b');item.append(document.createTextNode(title+' '));bold.textContent=money(amount);item.append(bold);if(amount<0)item.className='loss';bar.append(item)}
  const breakdown=card.querySelector('[data-bld-breakdown]');breakdown.replaceChildren();
  for(const [heading,rows] of [['Cost',[['Processing',processing],...(field(card,'training.enabled').checked?[['Immigration training',training]]:[]),...(field(card,'others.enabled').checked?[[value(card,'others.title')||'Others',other]]:[]),['Total cost',cost,'total']]],['Charge',[['Package',price],['Total charge',price,'total'],['Profit = Charge − Cost',profit,profit<0?'total loss':'total profit']]]]){
   const section=document.createElement('div'),title=document.createElement('h5');title.textContent=heading;section.append(title);
   for(const [name,amount,className] of rows){const row=document.createElement('p'),text=document.createElement('span'),number=document.createElement('span');text.textContent=name;number.textContent=money(amount);row.className=className||'';row.append(text,number);section.append(row)}breakdown.append(section);
  }
 }
 function syncServicePayment(card,key,fromStatus=false){
  const status=card.querySelector(`[data-bld-pay-status="${key}"]`),amount=Number(value(card,key+'.amount')),paid=field(card,key+'.paid_amount');
  if(fromStatus){if(status.value==='paid')paid.value=amount;if(status.value==='due')paid.value=0;if(status.value==='partial' && Number(paid.value)>=amount)paid.value=0;}
  else status.value=Number(paid.value)>0 ? (Number(paid.value)>=amount?'paid':'partial') : 'due';
  const hasPayment=Number(paid.value)>0,paymentDate=field(card,key+'.payment_date'),enabled=field(card,key+'.enabled').checked;
  card.querySelector(`[data-bld-due-date="${key}"]`).hidden=status.value==='paid';card.querySelector(`[data-bld-payment-date="${key}"]`).hidden=!hasPayment;
  paymentDate.disabled=!enabled||!hasPayment;
  if(hasPayment&&!paymentDate.value)paymentDate.value=value(card,key+'.incurred_on')||value(card,'submit_date');
 }
function totals(){refreshSelects();let charge=0,cost=0;cards.querySelectorAll('.cfbld-card').forEach(card=>{const packagePrice=Number(value(card,'client_price')),processing=Number(value(card,'visa_rate')),training=field(card,'training.enabled').checked ? Number(value(card,'training.amount')) : 0;const other=field(card,'others.enabled').checked ? Number(value(card,'others.amount')) : 0;paintCardSummary(card,packagePrice,processing+training+other);charge+=packagePrice;cost+=processing+training+other;paintMoney(card,packagePrice,processing,training,other);});modal.querySelector('#cfbld-total').textContent=`${cards.children.length} applicants · Cost ${money(cost)} · Charge ${money(charge)} · Profit ${money(charge-cost)}`;modal.querySelector('#cfbld-count').textContent=`${cards.children.length} applicant cards`;modal.querySelector('#cfbld-empty').hidden=cards.children.length>0;modal.querySelector('#cfbld-context').textContent=`${cards.children.length} applicants · Package ${money(charge)} · Profit ${money(charge-cost)}`}
 function page(next){step=next;modal.querySelectorAll('[data-bld-page]').forEach(el=>el.hidden=Number(el.dataset.bldPage)!==step);modal.querySelectorAll('[data-bld-step]').forEach(el=>{const on=Number(el.dataset.bldStep)===step;el.classList.toggle('is-on',on);el.setAttribute('aria-selected',String(on));el.tabIndex=on?0:-1});modal.querySelector('#cfbld-back').hidden=step===0;modal.querySelector('#cfbld-next').hidden=step===2;modal.querySelector('#cfbld-save').hidden=step!==2;error('');modal.querySelector('form').scrollTop=0;if(step===2)review();enhanceSelects();refreshSelects();}
 function check(){
  if(!cards.children.length){error('Add at least one applicant.');return false}
  for(const card of cards.children){
   collapse(card,false);
   for(const input of card.querySelectorAll('[required],input[type=number]')){if(!input.disabled && !input.checkValidity()){input.reportValidity();error('Complete the required applicant fields.');return false}}
   const training=field(card,'training.enabled').checked;
   if(field(card,'others.enabled').checked){
    if(Number(value(card,'others.paid_amount'))>Number(value(card,'others.amount')) || (Number(value(card,'others.paid_amount'))>0 && (!value(card,'others.bank_id')||!value(card,'others.payment_date')))){error('Other service payment must not exceed cost and requires bank and payment date.');return false}
   }
   if(Number(value(card,'cost_paid_amount'))>Number(value(card,'visa_rate')) || (Number(value(card,'cost_paid_amount'))>0 && !value(card,'cost_bank_id'))){error('Processing payment must not exceed cost and requires a bank.');return false}
   if(training && Number(value(card,'training.paid_amount'))>Number(value(card,'training.amount'))){error('Training payment cannot exceed training fee.');return false}
   if(training && Number(value(card,'training.paid_amount'))>0 && (!value(card,'training.bank_id')||!value(card,'training.payment_date'))){error('Select bank and payment date for paid training.');return false}
   for(const upload of field(card,'documents').files){if(upload.size>10*1024*1024){error('Each document must be 10 MB or smaller.');return false}}
  }return true;
 }
 function applyCategoryPreset(card){
  const category=categoryData.find(c=>String(c.id)===value(card,'contract_file_category_id'));
  if(category?.country_id)field(card,'country_id').value=String(category.country_id);
  if(setupUrl){applySetup(card);return}
  field(card,'visa_rate').value=category?.visa_rate ?? 0;
  checklist(card);totals();
 }
 // Setup (owner, 2026-10-25): the checklist and the figures for this country / visa type / job category,
 // from the same resolver the save uses (ContractFileSetup::resolve()). Documents already ticked stay
 // ticked; a figure someone typed (data-edited) is never overwritten — the flight's preset rule.
 function applySetup(card){
  const visa=card.querySelector('[data-field="contract_file_visa_type_id"]');
  const q=new URLSearchParams({country_id:value(card,'country_id'),visa_type_id:visa?.value||'',category_id:value(card,'contract_file_category_id')});
  card._setupReq?.abort();const ctrl=new AbortController();card._setupReq=ctrl;
  fetch(setupUrl+'?'+q,{headers:{Accept:'application/json'},signal:ctrl.signal,credentials:'same-origin'}).then(r=>r.ok?r.json():Promise.reject(new Error('setup'))).then(r=>{
   if(card._setupReq!==ctrl)return;
   paintChecklist(card,r.documents||[]);
   const fill=(name,v)=>{const input=field(card,name);if(!input||v===null||v===undefined||input.dataset.edited==='1')return;input.value=v;input.dispatchEvent(new Event('input',{bubbles:true}))};
   fill('visa_rate',r.figures?.processing_cost);
   fill('training.amount',r.figures?.training_fee);
   card.dataset.expectedDays=r.figures?.expected_days ?? '';
   applyExpected(card);
   const from=card.querySelector('[data-bld-setup-from]');
   const labels=[...new Set((r.sources||[]).filter(src=>src.documents.length).map(src=>src.label).concat(Object.values(r.figure_sources||{}).filter(Boolean)))];
   if(from){from.hidden=!labels.length;from.textContent=labels.length?'Filled from Setup: '+labels.join(' · '):''}
   totals();
  }).catch(e=>{if(e?.name==='AbortError')return;const category=categoryData.find(c=>String(c.id)===value(card,'contract_file_category_id'));if(field(card,'visa_rate').dataset.edited!=='1')field(card,'visa_rate').value=category?.visa_rate ?? 0;checklist(card);totals()});
 }
 function paintChecklist(card,names){
  const container=card.querySelector('[data-bld-checklist]');
  const ticked=new Set(Array.from(container.querySelectorAll('input:checked')).map(i=>i.value.toLowerCase()));
  container.replaceChildren();
  for(const name of names){const item=document.createElement('label');item.className='cfbld-check';const input=document.createElement('input');input.type='checkbox';input.name=`applicants[${card.dataset.index}][received_documents][]`;input.value=name;input.checked=ticked.has(name.toLowerCase());item.append(input,document.createTextNode(name));container.append(item)}
  if(!container.children.length)container.textContent='No checklist set up for this country, visa type or category yet — add one under Setup → Requirements.';
 }
 // Expected date = submit date + the preset's days, until someone types a date of their own.
 function applyExpected(card){
  const input=field(card,'expected_out_date'),days=card.dataset.expectedDays,submit=value(card,'submit_date');
  if(!input||input.dataset.edited==='1'||days===''||days===undefined||!submit)return;
  const d=new Date(submit+'T12:00:00');d.setDate(d.getDate()+Number(days));
  input.value=[d.getFullYear(),String(d.getMonth()+1).padStart(2,'0'),String(d.getDate()).padStart(2,'0')].join('-');
 }
 function checklist(card){
  const category=categoryData.find(c=>String(c.id)===value(card,'contract_file_category_id'));
  const container=card.querySelector('[data-bld-checklist]');container.replaceChildren();
  for(const name of category?.documents_list || []){const item=document.createElement('label');item.className='cfbld-check';const input=document.createElement('input');input.type='checkbox';input.name=`applicants[${card.dataset.index}][received_documents][]`;input.value=name;item.append(input,document.createTextNode(name));container.append(item)}
  if(!container.children.length)container.textContent='No checklist configured for this category.';
 }
 function add(id){
  if(Array.from(cards.children).some(c=>value(c,'passport_holder_id')===String(id)))return;
  if(cards.children.length>=30){error('Maximum 30 applicants per submission.');return}
  const holder=holders.find(h=>String(h.id)===String(id));if(!holder)return;
  const card=document.getElementById('cfbld-card-template').content.firstElementChild.cloneNode(true);card.dataset.index=String(sequence++);
  card.querySelectorAll('label').forEach(label=>{if(label.classList.contains('cfbld-check')||label.classList.contains('cfbld-svc-tick'))return;const text=Array.from(label.childNodes).filter(n=>n.nodeType===3&&n.textContent.trim());if(!text.length)return;const title=document.createElement('span');title.className='cfbld-lbl';title.textContent=text.map(n=>n.textContent.trim()).join(' ');text.forEach(n=>n.remove());label.prepend(title)});
  const header=card.querySelector('header'),body=card.querySelector('[data-bld-card-body]');body.id=`cfbld-applicant-body-${card.dataset.index}`;header.tabIndex=0;header.setAttribute('role','button');header.setAttribute('aria-controls',body.id);card.querySelector('[data-bld-collapse]').setAttribute('aria-controls',body.id);
  card.querySelector('[data-bld-name]').textContent=holder.name;
  card.querySelector('[data-bld-holder-info]').textContent=`Passport: ${holder.passport_no||'—'} · Phone: ${holder.phone||'—'} · Nationality: ${holder.nationality||'—'}`;
  const active=existing.filter(file=>String(file.passport_holder_id)===String(id) && !['delivered','case_closed'].includes(file.status) && !['cancelled','rejected'].includes(file.exception_flag));
  if(active.length){const warning=document.createElement('p');warning.style.color='#b45309';warning.textContent=`Existing active file(s): ${active.map(f=>f.file_number).join(', ')}. Check before creating another application.`;card.querySelector('[data-bld-holder-info]').after(warning)}
  card.querySelectorAll('[data-field]').forEach(input=>{const path=input.dataset.field.split('.');input.name=`applicants[${card.dataset.index}][${path.join('][')}]${input.multiple?'[]':''}`;if(input.type==='checkbox')input.value='1'});
  field(card,'passport_holder_id').value=id;
  field(card,'notes').value=modal.querySelector('#cfbld-default-notes').value;
  for(const [name,defaultName] of Object.entries({client_type:'type',client_id:'client',country_id:'country',contract_file_category_id:'category',vendor_id:'vendor',submit_date:'date'})) field(card,name).value=modal.querySelector('#cfbld-default-'+defaultName).value;
  field(card,'training.incurred_on').value=value(card,'submit_date');
  field(card,'others.incurred_on').value=value(card,'submit_date');
  field(card,'purchase_date').value=value(card,'submit_date');
  const matches=documents.filter(d=>String(d.passport_holder_id)===String(id)),container=card.querySelector('[data-bld-links]');
  matches.forEach(doc=>{const item=document.createElement('label');item.className='cfbld-check';const input=document.createElement('input');input.type='checkbox';input.name=`applicants[${card.dataset.index}][linked_documents][]`;input.value=doc.id;item.append(input,document.createTextNode(doc.title||doc.original_name));container.append(item)});
  if(!matches.length)container.textContent='No uploaded file documents for this passport holder. Upload below.';
  const toggleService=key=>{const enabled=field(card,key+'.enabled').checked;card.querySelector(`[data-bld-${key}]`).hidden=!enabled;card.querySelector(`[data-bld-service="${key}"]`).classList.toggle('is-selected',enabled);card.querySelectorAll(`[data-bld-service-enabled="${key}"]`).forEach(el=>el.hidden=!enabled);card.querySelectorAll(`[data-field^="${key}."]:not([data-field="${key}.enabled"])`).forEach(input=>input.disabled=!enabled);field(card,key+'.vendor_id').required=enabled;if(key==='others')field(card,'others.title').required=enabled;syncServicePayment(card,key);totals()};
  const toggle=()=>toggleService('training');
  field(card,'training.enabled').addEventListener('change',toggle);
  const toggleOthers=()=>toggleService('others');
  field(card,'others.enabled').addEventListener('change',toggleOthers);
  for(const key of ['training','others']){card.querySelector(`[data-bld-pay-status="${key}"]`).addEventListener('change',()=>{syncServicePayment(card,key,true);totals()});for(const suffix of ['amount','paid_amount'])field(card,key+'.'+suffix).addEventListener('input',()=>syncServicePayment(card,key));}
  field(card,'contract_file_category_id').addEventListener('change',()=>applyCategoryPreset(card));
  field(card,'country_id').addEventListener('change',()=>{const selected=field(card,'contract_file_category_id').selectedOptions[0];if(selected?.dataset.country && selected.dataset.country!==value(card,'country_id')){field(card,'contract_file_category_id').value='';applyCategoryPreset(card)}else if(setupUrl)applySetup(card)});
  if(setupUrl){
   card.querySelector('[data-field="contract_file_visa_type_id"]')?.addEventListener('change',()=>applySetup(card));
   // A figure or date someone types is theirs from then on — the preset never overwrites it.
   for(const name of ['visa_rate','training.amount','expected_out_date'])for(const type of ['input','change'])field(card,name)?.addEventListener(type,e=>{if(e.isTrusted)e.target.dataset.edited='1'});
   field(card,'submit_date').addEventListener('change',()=>applyExpected(card));
  }
  card.querySelector('[data-bld-remove]').addEventListener('click',()=>{card.remove();totals()});
  card.querySelector('[data-bld-collapse]').addEventListener('click',()=>{if(!saving)collapse(card,!body.hidden)});
  header.addEventListener('click',event=>{if(!saving && !event.target.closest('button'))collapse(card,!body.hidden)});
  header.addEventListener('keydown',event=>{if(saving || event.target!==header || !['Enter',' '].includes(event.key))return;event.preventDefault();collapse(card,!body.hidden)});
  const visaDefault=modal.querySelector('#cfbld-default-visa'),visaField=card.querySelector('[data-field="contract_file_visa_type_id"]');if(visaDefault&&visaField)visaField.value=visaDefault.value;
  card.addEventListener('input',totals);card.addEventListener('change',totals);cards.append(card);applyCategoryPreset(card);enhanceSelects(card);refreshSelects(card);collapse(card,false);toggle();toggleOthers();totals();
 }
 function review(){
  const container=modal.querySelector('#cfbld-review'),warnings=modal.querySelector('#cfbld-review-warnings'),all=Array.from(cards.querySelectorAll('.cfbld-card'));
  container.replaceChildren();warnings.replaceChildren();warnings.hidden=true;
  const make=(tag,text,className)=>{const el=document.createElement(tag);if(text!==undefined)el.textContent=text;if(className)el.className=className;return el};
  const show=(card,name)=>value(card,name)?label(card,name):'—';
  const date=text=>{if(!text)return '—';const [y,m,d]=text.split('-').map(Number);return new Date(y,m-1,d).toLocaleDateString('en-GB',{day:'2-digit',month:'short',year:'numeric'})};
  const open=(card,input)=>{page(1);collapse(card,false);card.scrollIntoView({behavior:'smooth',block:'center'});if(input&&!input.disabled)input.focus({preventScroll:true})};
  const summarize=values=>{const unique=[...new Set(values.filter(Boolean))];return unique.length>3?unique.slice(0,3).join(', ')+` … +${unique.length-3} more`:unique.join(', ')||'—'};
  const issues=[];let totalCost=0,totalCharge=0,received=0,required=0,uploads=0,linked=0,paid=0;
  all.forEach((card,index)=>{
   const processing=Number(value(card,'visa_rate')),training=field(card,'training.enabled').checked?Number(value(card,'training.amount')):0,other=field(card,'others.enabled').checked?Number(value(card,'others.amount')):0,cost=processing+training+other,charge=Number(value(card,'client_price'));
   const holder=holders.find(h=>String(h.id)===value(card,'passport_holder_id')),name=card.querySelector('[data-bld-name]').textContent;
   const checks=Array.from(card.querySelectorAll('[data-bld-checklist] input')),done=checks.filter(input=>input.checked).length,missing=checks.filter(input=>!input.checked);
   const uploadCount=field(card,'documents').files.length,linkCount=card.querySelectorAll('[data-bld-links] input:checked').length;
   totalCost+=cost;totalCharge+=charge;received+=done;required+=checks.length;uploads+=uploadCount;linked+=linkCount;paid+=Number(value(card,'cost_paid_amount'));
   for(const key of ['training','others'])if(field(card,key+'.enabled').checked)paid+=Number(value(card,key+'.paid_amount'));
   const invalid=Array.from(card.querySelectorAll('[required],input[type=number]')).filter(input=>!input.disabled&&!input.checkValidity());
   if(invalid.length)issues.push({card,input:invalid[0],blocker:true,text:`${name}: complete ${invalid.map(input=>input.closest('label')?.querySelector('.cfbld-lbl')?.textContent||input.dataset.field).join(', ')}`});
   if(Number(value(card,'cost_paid_amount'))>processing || (Number(value(card,'cost_paid_amount'))>0&&!value(card,'cost_bank_id')))issues.push({card,input:field(card,'cost_paid_amount'),blocker:true,text:`${name}: check processing payment amount / bank.`});
   for(const key of ['training','others'])if(field(card,key+'.enabled').checked){const amount=Number(value(card,key+'.amount')),payment=Number(value(card,key+'.paid_amount'));if(payment>amount || (payment>0&&(!value(card,key+'.bank_id')||!value(card,key+'.payment_date'))))issues.push({card,input:field(card,key+'.paid_amount'),blocker:true,text:`${name}: check ${key} payment amount, bank and payment date.`})}
   if(missing.length)issues.push({card,input:missing[0],blocker:false,text:`${name}: documents pending — ${missing.map(input=>input.value).join(', ')}`});
   const row=make('tr');
   const cell=(text,className)=>{const td=make('td',text,className);row.append(td);return td};
   cell(String(index+1));
   const applicant=cell();applicant.append(make('b',name),make('small',holder?.passport_no||'No passport'));
   const details=cell(show(card,'country_id'));details.append(make('small',show(card,'contract_file_category_id')));if(card.querySelector('[data-field="contract_file_visa_type_id"]')?.value)details.append(make('small',show(card,'contract_file_visa_type_id')));details.append(make('small','Submit '+date(value(card,'submit_date'))));if(value(card,'expected_out_date'))details.append(make('small','Expected '+date(value(card,'expected_out_date'))));
   const client=cell(show(card,'client_id'));client.append(make('small',label(card,'client_type')));
   const services=cell('Processing');services.append(make('small',show(card,'vendor_id')));
   for(const key of ['training','others'])if(field(card,key+'.enabled').checked){services.append(make('small',(key==='training'?'Immigration training':value(card,'others.title')||'Others')+' · '+show(card,key+'.vendor_id')))}
   const docs=cell(checks.length?`${done} / ${checks.length} received`:'No checklist',missing.length?'pending':'');docs.append(make('small',`${uploadCount} uploads · ${linkCount} linked`));
   const costs=cell(money(cost),'amount');costs.append(make('small','Processing '+money(processing)));if(training)costs.append(make('small','Training '+money(training)));if(other)costs.append(make('small','Others '+money(other)));
   cell(money(charge),'amount');cell(money(charge-cost),charge<cost?'amount loss':'amount profit');
   const actions=cell(),edit=make('button',undefined,'cfbld-review-edit');edit.type='button';edit.title='Edit '+name;edit.setAttribute('aria-label','Edit '+name);const icon=make('i');icon.className='fas fa-pen';edit.append(icon);edit.addEventListener('click',()=>open(card));actions.append(edit);container.append(row);
  });
  if(!all.length){const row=make('tr'),cell=make('td','No applicants added yet. Open Applicants & Agent to add a passport holder.');cell.colSpan=10;row.append(cell);container.append(row)}
  modal.querySelector('#cfbld-review-count').textContent=String(all.length);
  const summary=(id,rows)=>{const root=modal.querySelector(id);root.replaceChildren();for(const [title,text] of rows){const value=make('dd',text);value.title=text;root.append(make('dt',title),value)}};
  summary('#cfbld-review-setup',[['Countries',summarize(all.map(card=>show(card,'country_id')))],['Categories',summarize(all.map(card=>show(card,'contract_file_category_id')))],['Submission',summarize(all.map(card=>date(value(card,'submit_date'))))],['Documents',`${received} / ${required} checklist · ${uploads} uploads · ${linked} links`]]);
  summary('#cfbld-review-posting',[['Agent / Client',summarize(all.map(card=>show(card,'client_id')))],['Files to create',String(all.length)+' independent files'],['Provider payment now',money(paid)],['Provider payable',money(Math.max(0,totalCost-paid))]]);
  const totals=modal.querySelector('#cfbld-review-totals');totals.replaceChildren();for(const [title,amount] of [['Applicants',all.length],['Package charge',totalCharge],['File cost',totalCost],['Profit',totalCharge-totalCost]]){const box=make('div'),number=make('b',title==='Applicants'?String(amount):money(amount),amount<0?'loss':'');box.append(make('small',title),number);totals.append(box)}
  if(issues.length){warnings.hidden=false;const blockers=issues.filter(issue=>issue.blocker).length;warnings.append(make('b',blockers?`Required information missing — ${blockers} item(s). Complete before saving.`:'Documents pending — files can be saved; checklist remains incomplete.'));for(const issue of issues){const button=make('button',issue.text+' →',issue.blocker?'blocker':'');button.type='button';button.addEventListener('click',()=>open(issue.card,issue.input));warnings.append(button)}}
 }
 function setClient(id){modal.querySelector('#cfbld-default-client').value=id;refreshSelects();}
 function submissionToken(){const bytes=crypto.getRandomValues(new Uint8Array(16));bytes[6]=(bytes[6]&15)|64;bytes[8]=(bytes[8]&63)|128;const hex=Array.from(bytes,b=>b.toString(16).padStart(2,'0')).join('');return `${hex.slice(0,8)}-${hex.slice(8,12)}-${hex.slice(12,16)}-${hex.slice(16,20)}-${hex.slice(20)}`}
 function initPicker(){if(!window.jQuery?.fn.select2)return;const picker=jQuery('#cfbld-holders');if(!picker.hasClass('select2-hidden-accessible'))picker.select2({dropdownParent:jQuery(modal),width:'100%',placeholder:'Select passport holder...',allowClear:true,closeOnSelect:true});picker.off('change.cfbldAuto').on('change.cfbldAuto',()=>selectHolder(picker.val()));picker.val('').trigger('change.select2');modal.querySelector('[data-bld-search-fallback]').hidden=true;}
 function selectHolder(id){
  if(!id)return;
  error('');add(id);
  const picker=modal.querySelector('#cfbld-holders');picker.value='';
  if(window.jQuery?.fn.select2)jQuery(picker).trigger('change.select2');
 }
 function registerHolder(holder){
  if(!holders.some(item=>String(item.id)===String(holder.id)))holders.push(holder);
  const picker=modal.querySelector('#cfbld-holders');
  if(!Array.from(picker.options).some(option=>String(option.value)===String(holder.id)))picker.append(new Option(`${holder.name} · ${holder.passport_no||''} · ${holder.phone||''}`,holder.id));
 }
 window.CFFileBuilder={open(){previousFocus=document.activeElement;form.reset();cards.replaceChildren();sequence=0;token=submissionToken();modal.querySelectorAll('#cfbld-holders option').forEach(option=>option.hidden=false);modal.hidden=false;page(0);initPicker();enhanceSelects();refreshSelects();totals();modal.querySelector('#cfbld-default-type').focus()},setClient,registerHolder};
 modal.querySelectorAll('[data-bld-close]').forEach(button=>button.addEventListener('click',()=>{if(saving)return;modal.hidden=true;previousFocus?.focus()}));
 modal.addEventListener('keydown',event=>{if(event.key!=='Tab')return;const controls=Array.from(modal.querySelectorAll('button,input,select,textarea')).filter(el=>!el.disabled && el.getClientRects().length);const first=controls[0],last=controls[controls.length-1];if(event.shiftKey && document.activeElement===first){event.preventDefault();last.focus()}else if(!event.shiftKey && document.activeElement===last){event.preventDefault();first.focus()}});
 modal.querySelector('#cfbld-add').addEventListener('click',()=>openCfPhModal('cfbld-holders'));
 modal.querySelector('#cfbld-holder-search').addEventListener('input',event=>{const query=event.target.value.toLowerCase();modal.querySelectorAll('#cfbld-holders option').forEach(option=>option.hidden=!!option.value&&!option.textContent.toLowerCase().includes(query))});
 modal.querySelector('#cfbld-next').addEventListener('click',()=>page(Math.min(2,step+1)));
 modal.querySelectorAll('[data-bld-step]').forEach(tab=>{tab.addEventListener('click',()=>{if(!saving)page(Number(tab.dataset.bldStep))});tab.addEventListener('keydown',event=>{if(!['ArrowLeft','ArrowRight','Home','End'].includes(event.key)||saving)return;event.preventDefault();const next=event.key==='Home'?0:event.key==='End'?2:(step+(event.key==='ArrowRight'?1:2))%3;page(next);modal.querySelector(`[data-bld-step="${next}"]`).focus()})});
 modal.querySelector('#cfbld-back').addEventListener('click',()=>page(step-1));form.addEventListener('submit',event=>event.preventDefault());
 modal.querySelector('#cfbld-save').addEventListener('click',async()=>{
  if(saving)return;page(1);if(!check())return;page(2);const data=new FormData(form);data.set('request_token',token);saving=true;modal.querySelectorAll('button').forEach(b=>b.disabled=true);error('');
  try{const response=await fetch(submitUrl,{method:'POST',headers:{Accept:'application/json','X-CSRF-TOKEN':csrf},body:data});const result=await response.json();if(!response.ok || !result.success)throw new Error(result.message || 'Files could not be saved.');location.reload()}
  catch(e){error(e.message)}finally{saving=false;modal.querySelectorAll('button').forEach(b=>b.disabled=false)}
 });
})();
</script>
