(function($){
  'use strict';
  if (typeof RKS === 'undefined') return;
  function post(action, data){
    return $.post(RKS.ajax, $.extend({action:action, nonce:RKS.nonce}, data||{}));
  }
  function esc(s){return String(s||'').replace(/[&<>"]/g, m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[m]));}
  function toast(el, msg, ok){ $(el).html('<p class="'+(ok?'rks-ok':'rks-err')+'">'+esc(msg)+'</p>'); }

  if (RKS.page === 'rks') {
    post('rks_admin_data', {rks_action:'dashboard', days:30}).done(function(r){
      if(!r.success) return;
      var d=r.data, keys=[['total','تحويلات'],['whatsapp','واتساب'],['calls','اتصال'],['visitors','زوار'],['articles','مقالات حصرية'],['suspicious','مشبوه']];
      $('#rks-dash-cards').html(keys.map(k=>'<div class="rks-stat"><b>'+(d[k[0]]||0)+'</b><span>'+k[1]+'</span></div>').join(''));
    });
    $(document).on('click','[data-act=analyze]', function(){ post('rks_analyze_site').done(r=>alert(r.success?('النقاط '+r.data.score):r.data.msg)); });
  }

  /* Generator */
  var Q=[], stopped=false, idx=-1, done=0;
  function lines(id){return $(id).val().split('\n').map(s=>s.trim()).filter(Boolean);}
  function syncCount(){ var n=lines('#rks-svcs').length*lines('#rks-cities').length; $('#rks-matrix-count').text(n? (n+' مقال سيُولَّد حصرياً') : ''); }
  $('#rks-svcs,#rks-cities').on('input', syncCount);
  $('#rks-check').on('click', function(){
    var pairs=[]; lines('#rks-svcs').forEach(s=>lines('#rks-cities').forEach(c=>pairs.push({service:s,city:c})));
    post('rks_check_pairs',{pairs:JSON.stringify(pairs)}).done(function(r){
      if(!r.success) return;
      $('#rks-results').html(r.data.map(x=>'<div class="rks-item">'+(x.exists?'موجود':'جديد')+' — '+esc(x.service)+' / '+esc(x.city)+' (سابق: '+x.count+')</div>').join(''));
    });
  });
  $('#rks-start').on('click', function(){
    Q=[]; stopped=false; idx=-1; done=0;
    lines('#rks-svcs').forEach(s=>lines('#rks-cities').forEach(c=>Q.push({service:s,city:c})));
    if(!Q.length) return alert('أدخل خدمات ومدن');
    $('#rks-prog,#rks-stop').show(); $('#rks-results').empty(); next();
  });
  $('#rks-stop').on('click', function(){ stopped=true; });
  function next(){
    if(stopped) return;
    idx++;
    if(idx>=Q.length){ $('#rks-prog-txt').text('اكتمل '+done+' مقال'); return; }
    var it=Q[idx], pct=Math.round((idx/Q.length)*100);
    $('#rks-bar').css('width',pct+'%'); $('#rks-prog-txt').text('توليد حصري: '+it.service+' — '+it.city);
    post('rks_generate_article',{service:it.service,city:it.city,word_count:$('#rks-wc').val(),tone:$('#rks-tone').val()}).done(function(r){
      if(r.success){
        done++; var d=r.data;
        var card=$('<div class="rks-item"></div>').html(
          '<b>'+esc(d.title)+'</b> <span class="rks-ok">حصرية '+d.uniqueness+'%</span> — '+esc(d.angle)+' / '+esc(d.persona)+
          '<div>'+d.word_count+' كلمة</div><button class="rks-btn pub">حفظ كمسودة</button><div class="rks-out" style="max-height:220px;overflow:auto">'+d.html+'</div>'
        );
        card.find('.pub').on('click', function(){
          post('rks_publish_article', $.extend({}, d, {faq:JSON.stringify(d.faq||[]),features:JSON.stringify(d.features||[]),steps:JSON.stringify(d.steps||[]),category:$('#rks-cat').val(),status:'draft'}))
            .done(x=>alert(x.success?('تم: '+x.data.edit_url):x.data.msg));
        });
        $('#rks-results').prepend(card);
      } else {
        $('#rks-results').prepend('<div class="rks-item rks-err">فشل '+esc(it.service)+': '+esc(r.data&&r.data.msg)+'</div>');
      }
      setTimeout(next, parseInt($('#rks-delay').val(),10)||4000);
    }).fail(function(){ setTimeout(next, 2500); });
  }

  $('#rks-load-posts').on('click', function(){
    post('rks_list_posts').done(function(r){
      if(!r.success) return;
      $('#rks-posts').html(r.data.map(p=>'<div class="rks-item">'+esc(p.title)+' <button class="rks-btn rw" data-id="'+p.id+'">إعادة كتابة حصرية</button></div>').join(''));
    });
  });
  $(document).on('click','.rw', function(){
    var id=$(this).data('id');
    post('rks_rewrite_post',{post_id:id}).done(r=>alert(r.success?('حصرية '+r.data.uniqueness+'% — راجع المعاينة ثم طبّق'): (r.data.msg||'فشل')));
  });

  /* CSV */
  var csvReady=false;
  $('#rks-csv-file').on('change', function(){
    if(!this.files[0]) return;
    var fd=new FormData(); fd.append('action','rks_upload_csv'); fd.append('nonce',RKS.nonce); fd.append('csv_file', this.files[0]);
    $.ajax({url:RKS.ajax,method:'POST',data:fd,processData:false,contentType:false}).done(function(r){
      if(r.success){ csvReady=true; $('#rks-csv-go').prop('disabled',false); $('#rks-csv-info').text(r.data.total+' صف / '+r.data.headers.length+' عمود'); }
      else alert(r.data.msg);
    });
  });
  $('#rks-csv-go').on('click', function loop(){
    post('rks_process_csv',{skip_existing:$('#rks-skip').is(':checked'),update_existing:$('#rks-upd').is(':checked'),dry_run:$('#rks-dry').is(':checked')}).done(function(r){
      if(!r.success) return;
      $('#rks-csv-log').prepend('<div class="rks-item">'+r.data.offset+' / '+r.data.total+'</div>');
      if(!r.data.done) loop();
    });
  });

  $('#rks-tr-go').on('click', function(){
    post('rks_translate',{text:$('#rks-tr-in').val(),from:$('#rks-tr-from').val(),to:$('#rks-tr-to').val()}).done(r=>$('#rks-tr-out').text(r.success?r.data.text:(r.data.msg||'فشل')));
  });

  function loadConv(){
    post('rks_admin_data',{rks_action:'conversions',days:$('#rks-days').val(),search:$('#rks-q').val(),click_type:$('#rks-type').val()}).done(function(r){
      if(!r.success) return;
      var rows=(r.data.rows||[]).map(x=>'<tr><td>'+esc(x.click_type)+'</td><td>'+esc(x.phone_raw)+'</td><td>'+esc(x.city)+'</td><td>'+esc(x.page_title)+'</td><td>'+esc(x.ip)+'</td></tr>').join('');
      $('#rks-table').html('<table class="rks-table"><thead><tr><th>نوع</th><th>رقم</th><th>مدينة</th><th>صفحة</th><th>IP</th></tr></thead><tbody>'+rows+'</tbody></table>');
    });
  }
  $('#rks-load-conv').on('click', loadConv);
  $('#rks-exp').attr('href', RKS.ajax+'?action=rks_export_csv&nonce='+RKS.exp+'&days=30');

  function loadNums(){
    post('rks_list_numbers').done(function(r){
      if(!r.success) return;
      $('#nm-list').html((r.data||[]).map(n=>'<div class="rks-item">'+esc(n.label)+' — '+esc(n.phone)+' <button class="rks-btn danger deln" data-id="'+n.id+'">حذف</button></div>').join(''));
      $('#dm-num').html((r.data||[]).map(n=>'<option value="'+n.id+'">'+esc(n.label||n.phone)+'</option>').join(''));
    });
    post('rks_list_dni').done(function(r){
      if(!r.success) return;
      $('#dm-list').html((r.data||[]).map(x=>'<div class="rks-item">'+esc(x.source_type)+' → '+esc(x.label)+' <button class="rks-btn danger deld" data-id="'+x.id+'">حذف</button></div>').join(''));
    });
  }
  if (RKS.page==='rks-numbers') loadNums();
  $('#nm-save').on('click', function(){ post('rks_save_number',{label:$('#nm-label').val(),phone:$('#nm-phone').val(),wa_number:$('#nm-wa').val(),type:$('#nm-type').val()}).done(loadNums); });
  $(document).on('click','.deln', function(){ post('rks_delete_number',{id:$(this).data('id')}).done(loadNums); });
  $('#dm-save').on('click', function(){ post('rks_save_dni',{number_id:$('#dm-num').val(),source_type:$('#dm-src').val(),utm_source:$('#dm-us').val()}).done(loadNums); });
  $(document).on('click','.deld', function(){ post('rks_delete_dni',{id:$(this).data('id')}).done(loadNums); });

  $('#bl-go').on('click', function(){ post('rks_block_ip',{ip:$('#bl-ip').val()}).done(()=>post('rks_admin_data',{rks_action:'fraud'}).done(r=>$('#fr-box').html(JSON.stringify(r.data,null,2)))); });
  if (RKS.page==='rks-fraud') post('rks_admin_data',{rks_action:'fraud'}).done(r=>$('#fr-box').html('<pre class="rks-out">'+esc(JSON.stringify(r.data,null,2))+'</pre>'));

  $('#hm-go').on('click', function(){
    post('rks_heatmap_data',{page_url:$('#hm-url').val()}).done(function(r){
      var c=document.getElementById('hm-c'); if(!c||!r.success) return;
      var ctx=c.getContext('2d'); ctx.clearRect(0,0,c.width,c.height);
      (r.data.points||[]).forEach(function(p){ var x=p.x_pct/100*c.width,y=p.y_pct/100*c.height; var g=ctx.createRadialGradient(x,y,0,x,y,18); g.addColorStop(0,'rgba(239,68,68,.8)'); g.addColorStop(1,'rgba(0,0,0,0)'); ctx.fillStyle=g; ctx.beginPath(); ctx.arc(x,y,18,0,Math.PI*2); ctx.fill(); });
    });
  });

  $('#rp-go').on('click', function(){ post('rks_generate_report',{title:$('#rp-t').val(),days:$('#rp-d').val()}).done(r=>{ if(r.success) $('#rp-box').html('<a target="_blank" href="'+r.data.url+'">'+r.data.url+'</a>'); }); });
  if (RKS.page==='rks-reports') post('rks_list_reports').done(r=>{ if(r.success) $('#rp-box').append((r.data||[]).map(x=>'<div class="rks-item">'+esc(x.title)+'</div>').join('')); });

  $('#seo-go').on('click', function(){ post('rks_gen_seo',{keyword:$('#seo-kw').val()}).done(r=>$('#seo-out').text(JSON.stringify(r.data,null,2))); });
  $('#lk-scan').on('click', function(){
    post('rks_scan_links').done(function(r){
      $('#lk-box').html((r.data||[]).map(x=>'<div class="rks-item">'+esc(x.source)+' → '+esc(x.target)+' <button class="rks-btn apl" data-s="'+x.source_id+'" data-t="'+x.target_id+'" data-a="'+esc(x.anchor)+'">تطبيق</button></div>').join(''));
    });
  });
  $(document).on('click','.apl', function(){ post('rks_apply_link',{source_id:$(this).data('s'),target_id:$(this).data('t'),anchor:$(this).data('a')}).done(x=>alert(x.data.msg||'ok')); });
  $('#cp-go').on('click', function(){ post('rks_analyze_url',{url:$('#cp-url').val(),keyword:$('#cp-kw').val()}).done(r=>$('#cp-out').text(JSON.stringify(r.data,null,2))); });
  $('#of-save').on('click', function(){ post('rks_save_offer',{title:$('#of-t').val(),body:$('#of-b').val()}).done(()=>post('rks_list_offers').done(r=>$('#of-list').html((r.data||[]).map(o=>'<div class="rks-item">'+esc(o.title)+'</div>').join('')))); });
  if (RKS.page==='rks-offers') post('rks_list_offers').done(r=>$('#of-list').html((r.data||[]).map(o=>'<div class="rks-item">'+esc(o.title)+' — '+esc(o.body)+'</div>').join('')));
  $('#an-go').on('click', function(){ post('rks_analyze_site').done(r=>{ if(!r.success) return; $('#an-out').html('<h3>النقاط '+r.data.score+'</h3>'+(r.data.issues||[]).map(i=>'<div class="rks-item">'+esc(i.title)+': '+esc(i.msg)+'</div>').join('')); }); });

  $('#rks-set').on('submit', function(e){
    e.preventDefault();
    var data=$(this).serializeArray().reduce((a,x)=>{a[x.name]=x.value;return a;},{});
    ['tracking_enabled','cookie_consent','heatmap_enabled','dni_enabled','sticky_bar','smart_popup','notify_telegram'].forEach(k=>{ if(!data[k]) data[k]=''; });
    post('rks_save_settings', data).done(r=>toast('#rks-set-msg', r.data.msg||'تم', r.success));
  });
  $('#rks-test-g').on('click', function(){ post('rks_test_ai',{which:'gemini',key:$('[name=gemini_key]').val()}).done(r=>toast('#rks-set-msg', r.data.msg, r.data.success)); });
  $('#rks-test-c').on('click', function(){ post('rks_test_ai',{which:'claude',key:$('[name=claude_key]').val()}).done(r=>toast('#rks-set-msg', r.data.msg, r.data.success)); });
})(jQuery);
