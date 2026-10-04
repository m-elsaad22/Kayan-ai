(function(){
  'use strict';
  if (typeof RKS_Track === 'undefined' || RKS_Track.blocked) return;
  function fp(){
    var s=navigator.userAgent+(screen.width|0)+(screen.height|0)+(navigator.language||'');
    var h=0; for (var i=0;i<s.length;i++){h=((h<<5)-h)+s.charCodeAt(i);h|=0;}
    var v=localStorage.getItem('rks_fp'); if(v) return v;
    v='rks'+(h>>>0).toString(16)+Date.now().toString(36).slice(-4);
    localStorage.setItem('rks_fp',v); return v;
  }
  var FP=fp();
  var SID=(function(){var k='rks_sid',v=sessionStorage.getItem(k); if(!v){v='ks'+Date.now().toString(36)+Math.random().toString(36).slice(2,6); sessionStorage.setItem(k,v);} return v;})();
  var UA=navigator.userAgent;
  var DEV={
    type:/Mobi|Android|iPhone/i.test(UA)?'mobile':/iPad|Tablet/i.test(UA)?'tablet':'desktop',
    os:/Windows/i.test(UA)?'Windows':/Mac OS X/.test(UA)?'macOS':/iPhone/.test(UA)?'iOS':/Android/.test(UA)?'Android':'Other',
    browser:/Edg\//.test(UA)?'Edge':/Chrome\//.test(UA)?'Chrome':/Firefox\//.test(UA)?'Firefox':'Other'
  };
  var P=new URLSearchParams(location.search);
  var UTM={src:P.get('utm_source')||'',med:P.get('utm_medium')||'',camp:P.get('utm_campaign')||''};
  var GCLID=P.get('gclid')||sessionStorage.getItem('rks_gclid')||'';
  if(P.get('gclid')) sessionStorage.setItem('rks_gclid',GCLID);
  function tsrc(ref,med,src){
    if(GCLID) return 'google_ads';
    if(med==='cpc'||med==='paid') return 'paid';
    if(med||src) return 'campaign';
    if(!ref) return 'direct';
    try{ var d=new URL(ref).hostname;
      if(/facebook|instagram|twitter|tiktok|snapchat|linkedin/i.test(d)) return 'social';
      if(/google|bing|yahoo/i.test(d)) return 'organic';
    }catch(e){}
    return 'referral';
  }
  var TSRC=tsrc(document.referrer,UTM.med,UTM.src);
  function send(action,data){
    var fd=new FormData(); fd.append('action',action); fd.append('nonce',RKS_Track.nonce);
    Object.keys(data).forEach(function(k){ fd.append(k, data[k]); });
    if(navigator.sendBeacon && action.indexOf('end')!==-1){ navigator.sendBeacon(RKS_Track.ajax, fd); return; }
    fetch(RKS_Track.ajax,{method:'POST',body:fd,credentials:'same-origin'});
  }
  send('rks_register_visit',{fp:FP,sid:SID,device_type:DEV.type,os:DEV.os,browser:DEV.browser,screen:screen.width+'x'+screen.height,lang:navigator.language||'',referrer:document.referrer,utm_source:UTM.src,utm_medium:UTM.med,utm_campaign:UTM.camp,traffic_src:TSRC,page_url:location.href,page_title:document.title});

  function phoneFrom(href){
    var m=(href||'').match(/tel:\+?([0-9\s\-\(\)\.]+)/i); if(m) return m[1].replace(/\D/g,'');
    var w=(href||'').match(/wa\.me\/\+?([0-9]+)/i); return w?w[1]:'';
  }
  function cool(p){ var t=localStorage.getItem('rks_cd_'+p); return t && (Date.now()-parseInt(t,10)) < (RKS_Track.cooldown||30)*60000; }
  document.addEventListener('click', function(e){
    var a=e.target.closest('a'); if(!a||!a.href) return;
    var type=null;
    if(/^tel:/i.test(a.href)) type='call';
    else if(/wa\.me|whatsapp\.com/i.test(a.href)) type='whatsapp';
    if(!type) return;
    var ph=phoneFrom(a.href); if(ph && cool(ph)) return; if(ph) localStorage.setItem('rks_cd_'+ph, Date.now());
    send('rks_track_click',{fp:FP,sid:SID,click_type:type,phone_number:ph,page_url:location.href,page_title:document.title,referrer:document.referrer,utm_source:UTM.src,utm_medium:UTM.med,utm_campaign:UTM.camp,traffic_src:TSRC,gclid:GCLID,device_type:DEV.type,browser:DEV.browser,os:DEV.os});
  }, true);

  var start=Date.now(), maxS=0;
  window.addEventListener('scroll', function(){ var p=Math.round((scrollY/(document.documentElement.scrollHeight-innerHeight||1))*100); if(p>maxS) maxS=Math.min(p,100); }, {passive:true});
  function end(){ send('rks_session_end',{sid:SID,duration:Math.round((Date.now()-start)/1000),scroll:maxS}); }
  window.addEventListener('pagehide', end);

  if (RKS_Track.hm){
    var q=[], t=null;
    document.addEventListener('click', function(e){
      q.push({x:Math.round(e.clientX/innerWidth*100), y:Math.round((e.clientY+scrollY)/Math.max(document.body.scrollHeight,1)*100), el:(e.target.tagName||'').slice(0,20)});
      clearTimeout(t); t=setTimeout(function(){ if(!q.length) return; send('rks_heatmap_batch',{sid:SID,page_url:location.href,points:JSON.stringify(q)}); q=[]; }, 2500);
    }, {passive:true});
  }
  if (RKS_Track.dni){
    var fd=new FormData(); fd.append('action','rks_get_dni'); fd.append('nonce',RKS_Track.nonce); fd.append('traffic_src',TSRC); fd.append('utm_source',UTM.src); fd.append('utm_campaign',UTM.camp);
    fetch(RKS_Track.ajax,{method:'POST',body:fd,credentials:'same-origin'}).then(r=>r.json()).then(function(r){
      if(!r||!r.success||!r.data) return;
      document.querySelectorAll('a[href^="tel:"]').forEach(function(a){ a.href='tel:+'+r.data.phone; });
      if(r.data.wa_number) document.querySelectorAll('a[href*="wa.me"]').forEach(function(a){ a.href=a.href.replace(/wa\.me\/\+?[0-9]+/,'wa.me/'+r.data.wa_number); });
    }).catch(function(){});
  }
})();
