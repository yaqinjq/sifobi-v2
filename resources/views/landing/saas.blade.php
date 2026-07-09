<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>SIFOBI — Sistem Inventori F&B Modern</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --green-900:#0a1f12;--green-800:#0f2918;--green-700:#1B4332;--green-600:#2D6A4F;
  --green-500:#40916C;--green-400:#52B788;--green-200:#B7E4C7;--green-100:#D8F3DC;--green-50:#F0FFF4;
  --amber:#D97706;--amber-light:#FEF3C7;--amber-dark:#92400E;
  --white:#FFFFFF;--gray-50:#F8FAFC;--gray-100:#F1F5F9;--gray-200:#E2E8F0;
  --gray-400:#94A3B8;--gray-600:#475569;--gray-800:#1E293B;--gray-900:#0F172A;
  --font-head:'Plus Jakarta Sans',sans-serif;
  --font-body:'Inter',sans-serif;
}
html{scroll-behavior:smooth}
body{font-family:var(--font-body);color:var(--gray-900);background:var(--white);overflow-x:hidden}

/* ── NAVBAR ── */
nav{position:fixed;top:0;left:0;right:0;z-index:100;padding:0 5%;background:rgba(255,255,255,0.92);backdrop-filter:blur(12px);border-bottom:1px solid var(--gray-100)}
.nav-inner{max-width:1200px;margin:0 auto;height:68px;display:flex;align-items:center;justify-content:space-between}
.nav-logo{display:flex;align-items:center;gap:10px;text-decoration:none}
.nav-logo-icon{width:36px;height:36px;background:var(--green-700);border-radius:10px;display:flex;align-items:center;justify-content:center;font-family:var(--font-head);font-weight:800;color:var(--white);font-size:14px;letter-spacing:-0.5px}
.nav-logo-text{font-family:var(--font-head);font-weight:800;font-size:20px;color:var(--green-800);letter-spacing:-0.5px}
.nav-logo-sub{font-size:11px;color:var(--gray-400);font-weight:400;letter-spacing:0}
.nav-links{display:flex;align-items:center;gap:32px}
.nav-links a{text-decoration:none;color:var(--gray-600);font-size:14px;font-weight:500;transition:color 0.2s}
.nav-links a:hover{color:var(--green-700)}
.nav-cta{display:flex;align-items:center;gap:12px}
.btn-ghost{padding:8px 20px;border:1.5px solid var(--gray-200);border-radius:10px;font-size:14px;font-weight:600;color:var(--gray-700);text-decoration:none;transition:all 0.2s;font-family:var(--font-body)}
.btn-ghost:hover{border-color:var(--green-500);color:var(--green-700)}
.btn-primary{padding:10px 24px;background:var(--green-700);border:none;border-radius:10px;font-size:14px;font-weight:600;color:var(--white);text-decoration:none;cursor:pointer;transition:all 0.2s;font-family:var(--font-body)}
.btn-primary:hover{background:var(--green-600);transform:translateY(-1px)}
.btn-primary-lg{padding:14px 32px;font-size:16px;border-radius:14px}
.btn-outline-lg{padding:13px 32px;border:2px solid var(--white);border-radius:14px;font-size:16px;font-weight:600;color:var(--white);text-decoration:none;transition:all 0.2s;font-family:var(--font-body)}
.btn-outline-lg:hover{background:rgba(255,255,255,0.15)}

/* ── HERO ── */
.hero{padding:140px 5% 100px;background:linear-gradient(160deg,var(--green-900) 0%,var(--green-700) 55%,var(--green-600) 100%);min-height:100vh;display:flex;align-items:center;position:relative;overflow:hidden}
.hero::before{content:'';position:absolute;inset:0;background:radial-gradient(ellipse 60% 80% at 70% 50%, rgba(64,145,108,0.25) 0%, transparent 60%)}
.hero-grid{position:absolute;inset:0;background-image:linear-gradient(rgba(255,255,255,0.03) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,0.03) 1px,transparent 1px);background-size:48px 48px;pointer-events:none}
.hero-inner{max-width:1200px;margin:0 auto;display:grid;grid-template-columns:1fr 1fr;gap:80px;align-items:center;position:relative;z-index:1}
.hero-badge{display:inline-flex;align-items:center;gap:8px;background:rgba(255,255,255,0.1);border:1px solid rgba(255,255,255,0.2);border-radius:100px;padding:6px 16px 6px 8px;margin-bottom:28px}
.hero-badge-dot{width:8px;height:8px;background:#4ade80;border-radius:50%;animation:pulse 2s infinite}
.hero-badge-text{font-size:13px;color:rgba(255,255,255,0.85);font-weight:500}
@keyframes pulse{0%,100%{opacity:1}50%{opacity:0.5}}
.hero h1{font-family:var(--font-head);font-size:clamp(36px,4.5vw,58px);font-weight:800;color:var(--white);line-height:1.15;letter-spacing:-1.5px;margin-bottom:24px}
.hero h1 em{font-style:normal;color:#4ade80}
.hero-desc{font-size:18px;color:rgba(255,255,255,0.72);line-height:1.7;margin-bottom:40px;max-width:480px}
.hero-actions{display:flex;gap:16px;flex-wrap:wrap;align-items:center}
.hero-trust{margin-top:48px;display:flex;align-items:center;gap:16px}
.hero-trust-text{font-size:13px;color:rgba(255,255,255,0.5)}
.hero-trust-brands{display:flex;gap:12px;align-items:center;flex-wrap:wrap}
.hero-trust-brand{font-size:12px;font-weight:600;color:rgba(255,255,255,0.65);background:rgba(255,255,255,0.08);padding:5px 14px;border-radius:100px;border:1px solid rgba(255,255,255,0.12)}

/* ── HERO VISUAL ── */
.hero-visual{position:relative}
.dashboard-frame{background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.12);border-radius:20px;padding:20px;backdrop-filter:blur(8px)}
.dash-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px}
.dash-title{font-family:var(--font-head);font-size:13px;font-weight:700;color:rgba(255,255,255,0.9)}
.dash-outlet{font-size:11px;color:rgba(255,255,255,0.5);background:rgba(255,255,255,0.1);padding:3px 10px;border-radius:100px}
.kpi-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:14px}
.kpi-card{background:rgba(255,255,255,0.08);border-radius:12px;padding:12px 14px;border:1px solid rgba(255,255,255,0.08)}
.kpi-label{font-size:10px;color:rgba(255,255,255,0.5);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:4px}
.kpi-val{font-family:var(--font-head);font-size:22px;font-weight:800;color:var(--white)}
.kpi-val.green{color:#4ade80}
.kpi-val.amber{color:#fbbf24}
.stock-list{display:flex;flex-direction:column;gap:8px}
.stock-item{display:flex;align-items:center;gap:10px;padding:9px 12px;background:rgba(255,255,255,0.06);border-radius:10px;border:1px solid rgba(255,255,255,0.07)}
.stock-dot{width:8px;height:8px;border-radius:50%;flex-shrink:0}
.stock-dot.ok{background:#4ade80}
.stock-dot.warn{background:#fbbf24}
.stock-dot.low{background:#f87171}
.stock-name{flex:1;font-size:12px;color:rgba(255,255,255,0.8);font-weight:500}
.stock-qty{font-size:11px;color:rgba(255,255,255,0.5);font-family:var(--font-head)}
.stock-bar-wrap{width:60px;height:4px;background:rgba(255,255,255,0.1);border-radius:2px;overflow:hidden}
.stock-bar{height:100%;border-radius:2px;transition:width 1s ease}
.floating-notif{position:absolute;top:-16px;right:-16px;background:var(--white);border-radius:14px;padding:12px 16px;box-shadow:0 8px 24px rgba(0,0,0,0.3);min-width:180px;animation:floatUp 3s ease-in-out infinite}
@keyframes floatUp{0%,100%{transform:translateY(0)}50%{transform:translateY(-6px)}}
.notif-label{font-size:10px;color:var(--gray-400);font-weight:500;margin-bottom:4px;text-transform:uppercase;letter-spacing:0.5px}
.notif-val{font-family:var(--font-head);font-size:18px;font-weight:800;color:var(--green-700)}
.notif-sub{font-size:11px;color:var(--gray-400)}
.floating-alert{position:absolute;bottom:-16px;left:-24px;background:var(--amber-light);border-left:3px solid var(--amber);border-radius:10px;padding:10px 14px;min-width:160px;animation:floatDown 3.5s ease-in-out infinite}
@keyframes floatDown{0%,100%{transform:translateY(0)}50%{transform:translateY(5px)}}
.alert-icon{font-size:12px;margin-bottom:2px}
.alert-text{font-size:12px;font-weight:600;color:var(--amber-dark)}
.alert-sub{font-size:11px;color:var(--amber-dark);opacity:0.7}

/* ── STATS ── */
.stats{padding:60px 5%;background:var(--gray-50);border-bottom:1px solid var(--gray-100)}
.stats-inner{max-width:1200px;margin:0 auto;display:grid;grid-template-columns:repeat(4,1fr);gap:0;text-align:center}
.stat-item{padding:32px 24px;border-right:1px solid var(--gray-200)}
.stat-item:last-child{border-right:none}
.stat-num{font-family:var(--font-head);font-size:40px;font-weight:800;color:var(--green-700);letter-spacing:-1px;line-height:1}
.stat-label{font-size:14px;color:var(--gray-600);margin-top:6px;line-height:1.4}

/* ── SECTION COMMON ── */
section{padding:100px 5%}
.section-inner{max-width:1200px;margin:0 auto}
.section-eyebrow{font-size:13px;font-weight:700;color:var(--green-600);text-transform:uppercase;letter-spacing:1px;margin-bottom:14px}
.section-title{font-family:var(--font-head);font-size:clamp(28px,3.5vw,44px);font-weight:800;color:var(--gray-900);letter-spacing:-1px;line-height:1.2;margin-bottom:18px}
.section-desc{font-size:17px;color:var(--gray-600);line-height:1.7;max-width:560px}

/* ── FITUR UTAMA ── */
.features{background:var(--white)}
.features-header{text-align:center;margin-bottom:64px}
.features-header .section-desc{margin:0 auto}
.features-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:28px}
.feat-card{padding:32px;border:1px solid var(--gray-200);border-radius:20px;transition:all 0.25s;cursor:default;position:relative;overflow:hidden}
.feat-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:linear-gradient(90deg,var(--green-500),var(--green-400));opacity:0;transition:opacity 0.25s}
.feat-card:hover{border-color:var(--green-400);box-shadow:0 8px 32px rgba(27,67,50,0.08);transform:translateY(-2px)}
.feat-card:hover::before{opacity:1}
.feat-icon{width:48px;height:48px;border-radius:14px;background:var(--green-50);display:flex;align-items:center;justify-content:center;margin-bottom:20px}
.feat-icon svg{width:24px;height:24px;stroke:var(--green-600);fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.feat-title{font-family:var(--font-head);font-size:18px;font-weight:700;color:var(--gray-900);margin-bottom:10px}
.feat-desc{font-size:14px;color:var(--gray-600);line-height:1.7}

/* ── HOW IT WORKS ── */
.how{background:var(--gray-50)}
.how-layout{display:grid;grid-template-columns:1fr 1fr;gap:80px;align-items:center}
.how-steps{display:flex;flex-direction:column;gap:0}
.how-step{display:flex;gap:20px;padding:24px 0;border-bottom:1px solid var(--gray-200);cursor:pointer;transition:all 0.2s}
.how-step:last-child{border-bottom:none}
.how-step-num{width:40px;height:40px;border-radius:12px;background:var(--gray-200);color:var(--gray-500);font-family:var(--font-head);font-weight:800;font-size:16px;display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:all 0.2s}
.how-step.active .how-step-num{background:var(--green-700);color:var(--white)}
.how-step-content{}
.how-step-title{font-family:var(--font-head);font-size:16px;font-weight:700;color:var(--gray-500);margin-bottom:6px;transition:color 0.2s}
.how-step.active .how-step-title{color:var(--gray-900)}
.how-step-desc{font-size:14px;color:var(--gray-400);line-height:1.6;transition:color 0.2s}
.how-step.active .how-step-desc{color:var(--gray-600)}
.how-visual{background:var(--white);border-radius:24px;padding:32px;border:1px solid var(--gray-200);min-height:380px;display:flex;flex-direction:column;justify-content:center}
.how-visual-content{}
.how-screen{display:none}
.how-screen.active{display:block;animation:fadeIn 0.3s ease}
@keyframes fadeIn{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:translateY(0)}}

/* ── MOBILE SHOWCASE ── */
.mobile{background:var(--green-900);padding:100px 5%;overflow:hidden;position:relative}
.mobile::before{content:'';position:absolute;top:-100px;right:-100px;width:600px;height:600px;background:radial-gradient(circle,rgba(64,145,108,0.2) 0%,transparent 60%);pointer-events:none}
.mobile-inner{max-width:1200px;margin:0 auto;display:grid;grid-template-columns:1fr 1fr;gap:80px;align-items:center}
.mobile .section-eyebrow{color:var(--green-400)}
.mobile .section-title{color:var(--white)}
.mobile .section-desc{color:rgba(255,255,255,0.65)}
.mobile-features{margin-top:36px;display:flex;flex-direction:column;gap:18px}
.mobile-feat{display:flex;gap:14px;align-items:flex-start}
.mobile-feat-icon{width:36px;height:36px;border-radius:10px;background:rgba(255,255,255,0.08);display:flex;align-items:center;justify-content:center;flex-shrink:0}
.mobile-feat-icon svg{width:18px;height:18px;stroke:var(--green-400);fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.mobile-feat-title{font-weight:600;font-size:15px;color:var(--white);margin-bottom:3px}
.mobile-feat-desc{font-size:13px;color:rgba(255,255,255,0.55);line-height:1.5}
.phone-mockup{position:relative;display:flex;justify-content:center}
.phone-frame{width:260px;background:var(--gray-900);border-radius:40px;padding:14px;border:3px solid rgba(255,255,255,0.08);box-shadow:0 40px 80px rgba(0,0,0,0.5)}
.phone-notch{width:80px;height:24px;background:var(--gray-900);border-radius:0 0 14px 14px;margin:0 auto 12px;position:relative;z-index:1}
.phone-screen{background:var(--gray-50);border-radius:28px;overflow:hidden;min-height:480px}
.phone-topbar{background:var(--green-700);padding:14px 16px 20px;color:var(--white)}
.phone-greeting{font-size:10px;opacity:0.7;margin-bottom:2px}
.phone-name{font-family:var(--font-head);font-size:15px;font-weight:700}
.phone-outlet{font-size:10px;opacity:0.6;margin-top:1px}
.phone-kpis{display:grid;grid-template-columns:1fr 1fr;gap:8px;padding:12px}
.phone-kpi{background:var(--white);border-radius:12px;padding:10px;border:1px solid var(--gray-200)}
.phone-kpi-label{font-size:8px;color:var(--gray-400);text-transform:uppercase;letter-spacing:0.4px;margin-bottom:3px}
.phone-kpi-val{font-family:var(--font-head);font-size:16px;font-weight:800;color:var(--gray-900)}
.phone-section{padding:0 12px 12px}
.phone-section-title{font-size:10px;font-weight:600;color:var(--gray-500);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:8px}
.phone-item{display:flex;align-items:center;gap:8px;padding:8px 10px;background:var(--white);border-radius:10px;margin-bottom:6px;border:1px solid var(--gray-100)}
.phone-item-dot{width:7px;height:7px;border-radius:50%;flex-shrink:0}
.phone-item-name{flex:1;font-size:11px;font-weight:500;color:var(--gray-800)}
.phone-item-qty{font-size:10px;color:var(--gray-500);font-family:var(--font-head)}
.phone-nav{background:var(--white);border-top:1px solid var(--gray-200);padding:8px 16px;display:flex;justify-content:space-around;align-items:center}
.phone-nav-item{display:flex;flex-direction:column;align-items:center;gap:2px}
.phone-nav-icon{width:18px;height:18px;background:var(--gray-200);border-radius:4px}
.phone-nav-icon.active{background:var(--green-100)}
.phone-nav-label{font-size:8px;color:var(--gray-400)}
.phone-nav-label.active{color:var(--green-700);font-weight:600}

/* ── PRICING ── */
.pricing{background:var(--white)}
.pricing-header{text-align:center;margin-bottom:56px}
.pricing-header .section-desc{margin:0 auto}
.pricing-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:24px;max-width:960px;margin:0 auto}
.price-card{padding:36px 32px;border:1.5px solid var(--gray-200);border-radius:24px;position:relative;transition:all 0.25s}
.price-card.featured{border-color:var(--green-500);background:var(--green-50)}
.price-popular{position:absolute;top:-14px;left:50%;transform:translateX(-50%);background:var(--green-700);color:var(--white);font-size:12px;font-weight:700;padding:5px 18px;border-radius:100px;white-space:nowrap}
.price-name{font-family:var(--font-head);font-size:18px;font-weight:700;color:var(--gray-900);margin-bottom:4px}
.price-desc{font-size:13px;color:var(--gray-500);margin-bottom:24px;line-height:1.5}
.price-amount{margin-bottom:8px}
.price-currency{font-size:16px;font-weight:600;color:var(--gray-900);vertical-align:top;line-height:2.2}
.price-num{font-family:var(--font-head);font-size:48px;font-weight:800;color:var(--gray-900);letter-spacing:-2px;line-height:1}
.price-period{font-size:14px;color:var(--gray-400);margin-bottom:6px}
.price-per{font-size:12px;color:var(--gray-400);margin-bottom:28px}
.price-features{list-style:none;display:flex;flex-direction:column;gap:12px;margin-bottom:32px}
.price-features li{display:flex;gap:10px;font-size:14px;color:var(--gray-700);align-items:flex-start}
.price-check{width:18px;height:18px;background:var(--green-100);border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:1px}
.price-check svg{width:10px;height:10px;stroke:var(--green-700);stroke-width:2.5;fill:none}
.price-btn{display:block;text-align:center;padding:12px;border-radius:12px;font-size:15px;font-weight:600;text-decoration:none;transition:all 0.2s;font-family:var(--font-body)}
.price-btn-outline{border:1.5px solid var(--gray-300);color:var(--gray-700)}
.price-btn-outline:hover{border-color:var(--green-500);color:var(--green-700)}
.price-btn-solid{background:var(--green-700);color:var(--white)}
.price-btn-solid:hover{background:var(--green-600)}

/* ── TESTIMONIAL ── */
.testimonial{background:var(--gray-50);padding:100px 5%}
.testi-inner{max-width:1200px;margin:0 auto}
.testi-header{text-align:center;margin-bottom:56px}
.testi-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:24px}
.testi-card{background:var(--white);border-radius:20px;padding:28px;border:1px solid var(--gray-200)}
.testi-stars{display:flex;gap:4px;margin-bottom:16px}
.star{color:var(--amber);font-size:16px}
.testi-quote{font-size:15px;color:var(--gray-700);line-height:1.7;margin-bottom:20px;font-style:italic}
.testi-person{display:flex;gap:12px;align-items:center}
.testi-avatar{width:40px;height:40px;border-radius:50%;background:var(--green-200);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:14px;color:var(--green-800);flex-shrink:0}
.testi-name{font-weight:600;font-size:14px;color:var(--gray-900)}
.testi-role{font-size:12px;color:var(--gray-400)}

/* ── CTA ── */
.cta-section{background:linear-gradient(135deg,var(--green-900) 0%,var(--green-700) 100%);padding:100px 5%;text-align:center;position:relative;overflow:hidden}
.cta-section::before{content:'';position:absolute;top:-50%;left:50%;transform:translateX(-50%);width:800px;height:800px;background:radial-gradient(circle,rgba(255,255,255,0.04) 0%,transparent 60%)}
.cta-inner{max-width:640px;margin:0 auto;position:relative;z-index:1}
.cta-section h2{font-family:var(--font-head);font-size:clamp(28px,4vw,48px);font-weight:800;color:var(--white);letter-spacing:-1px;margin-bottom:18px;line-height:1.2}
.cta-section p{font-size:17px;color:rgba(255,255,255,0.7);line-height:1.7;margin-bottom:40px}
.cta-actions{display:flex;gap:16px;justify-content:center;flex-wrap:wrap}
.cta-note{margin-top:20px;font-size:13px;color:rgba(255,255,255,0.45)}

/* ── FOOTER ── */
footer{background:var(--gray-900);padding:60px 5% 36px;color:rgba(255,255,255,0.6)}
.footer-inner{max-width:1200px;margin:0 auto}
.footer-top{display:grid;grid-template-columns:2fr 1fr 1fr 1fr;gap:48px;margin-bottom:48px}
.footer-brand-name{font-family:var(--font-head);font-size:22px;font-weight:800;color:var(--white);margin-bottom:12px}
.footer-brand-desc{font-size:13px;line-height:1.7;max-width:280px}
.footer-col-title{font-size:12px;font-weight:600;color:var(--white);text-transform:uppercase;letter-spacing:0.8px;margin-bottom:16px}
.footer-links{list-style:none;display:flex;flex-direction:column;gap:10px}
.footer-links a{text-decoration:none;color:rgba(255,255,255,0.5);font-size:13px;transition:color 0.2s}
.footer-links a:hover{color:var(--green-400)}
.footer-bottom{border-top:1px solid rgba(255,255,255,0.08);padding-top:28px;display:flex;justify-content:space-between;align-items:center;font-size:13px}
.footer-copy{color:rgba(255,255,255,0.35)}

/* ── RESPONSIVE ── */
@media(max-width:968px){
  .nav-links{display:none}
  .hero-inner{grid-template-columns:1fr;gap:48px}
  .hero-visual{display:none}
  .stats-inner{grid-template-columns:repeat(2,1fr)}
  .stat-item{border-right:none;border-bottom:1px solid var(--gray-200)}
  .features-grid{grid-template-columns:1fr 1fr}
  .how-layout{grid-template-columns:1fr}
  .how-visual{display:none}
  .mobile-inner{grid-template-columns:1fr}
  .phone-mockup{display:none}
  .pricing-grid{grid-template-columns:1fr}
  .testi-grid{grid-template-columns:1fr}
  .footer-top{grid-template-columns:1fr 1fr}
  .footer-bottom{flex-direction:column;gap:12px;text-align:center}
}
@media(max-width:576px){
  .features-grid{grid-template-columns:1fr}
  .stats-inner{grid-template-columns:1fr 1fr}
}
</style>
</head>
<body>

<!-- NAVBAR -->
<nav>
  <div class="nav-inner">
    <a class="nav-logo" href="#">
      <div class="nav-logo-icon">SF</div>
      <div>
        <div class="nav-logo-text">SIFOBI</div>
        <div class="nav-logo-sub">Inventory F&B</div>
      </div>
    </a>
    <div class="nav-links">
      <a href="#fitur">Fitur</a>
      <a href="#cara-kerja">Cara Kerja</a>
      <a href="#harga">Harga</a>
      <a href="#testimoni">Testimoni</a>
    </div>
    <div class="nav-cta">
      <a href="{{ route('login') }}" class="btn-ghost">Masuk</a>
      <a href="{{ route('register') }}" class="btn-primary">Coba Gratis</a>
    </div>
  </div>
</nav>

<!-- HERO -->
<section class="hero">
  <div class="hero-grid"></div>
  <div class="hero-inner">
    <div>
      <div class="hero-badge">
        <span class="hero-badge-dot"></span>
        <span class="hero-badge-text">Dipercaya 50+ gerai F&B aktif</span>
      </div>
      <h1>Stok Terkendali,<br>Bisnis F&B Anda<br><em>Bergerak Lebih Cepat</em></h1>
      <p class="hero-desc">Platform inventori modern untuk restoran, kafe, dan gerai F&B multi-outlet. Catat, pantau, dan analisis stok bahan baku dari satu dasbor — di HP atau komputer.</p>
      <div class="hero-actions">
        <a href="{{ route('register') }}" class="btn-primary btn-primary-lg">Coba Gratis 14 Hari</a>
        <a href="#cara-kerja" class="btn-outline-lg">Lihat Demo</a>
      </div>
      <div class="hero-trust">
        <span class="hero-trust-text">Dipakai oleh:</span>
        <div class="hero-trust-brands">
          <span class="hero-trust-brand">My Kopi-O!</span>
          <span class="hero-trust-brand">Quali</span>
          <span class="hero-trust-brand">Kampong Melayu</span>
        </div>
      </div>
    </div>
    <div class="hero-visual">
      <div style="position:relative;padding:20px">
        <div class="dashboard-frame">
          <div class="dash-header">
            <span class="dash-title">Stok Harian — Shift Pagi</span>
            <span class="dash-outlet">MKO Grand City</span>
          </div>
          <div class="kpi-grid">
            <div class="kpi-card">
              <div class="kpi-label">Item Terpantau</div>
              <div class="kpi-val">47</div>
            </div>
            <div class="kpi-card">
              <div class="kpi-label">Nilai Stok</div>
              <div class="kpi-val green" style="font-size:16px">Rp 4.2jt</div>
            </div>
            <div class="kpi-card">
              <div class="kpi-label">Spoil Hari Ini</div>
              <div class="kpi-val amber">2</div>
            </div>
            <div class="kpi-card">
              <div class="kpi-label">Stok Menipis</div>
              <div class="kpi-val" style="color:#f87171">3</div>
            </div>
          </div>
          <div class="stock-list">
            <div class="stock-item">
              <div class="stock-dot ok"></div>
              <span class="stock-name">Susu UHT 1L</span>
              <span class="stock-qty">24 karton</span>
              <div class="stock-bar-wrap"><div class="stock-bar" style="width:78%;background:#4ade80"></div></div>
            </div>
            <div class="stock-item">
              <div class="stock-dot ok"></div>
              <span class="stock-name">Espresso Blend</span>
              <span class="stock-qty">8.5 kg</span>
              <div class="stock-bar-wrap"><div class="stock-bar" style="width:60%;background:#4ade80"></div></div>
            </div>
            <div class="stock-item">
              <div class="stock-dot warn"></div>
              <span class="stock-name">Sirup Hazelnut</span>
              <span class="stock-qty">2 botol</span>
              <div class="stock-bar-wrap"><div class="stock-bar" style="width:22%;background:#fbbf24"></div></div>
            </div>
            <div class="stock-item">
              <div class="stock-dot low"></div>
              <span class="stock-name">Gula Semut</span>
              <span class="stock-qty">350 gr</span>
              <div class="stock-bar-wrap"><div class="stock-bar" style="width:8%;background:#f87171"></div></div>
            </div>
          </div>
        </div>
        <div class="floating-notif">
          <div class="notif-label">Penerimaan masuk</div>
          <div class="notif-val">+12 karton</div>
          <div class="notif-sub">Susu UHT · barusan</div>
        </div>
        <div class="floating-alert">
          <div class="alert-icon"><svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="vertical-align:middle"><path d="M12 9v4"/><path d="M12 17h.01"/><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg></div>
          <div class="alert-text">Gula Semut kritis!</div>
          <div class="alert-sub">Sisa &lt; 500gr</div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- STATS -->
<div class="stats">
  <div class="stats-inner">
    <div class="stat-item">
      <div class="stat-num">50+</div>
      <div class="stat-label">Gerai F&B<br>terpantau aktif</div>
    </div>
    <div class="stat-item">
      <div class="stat-num">9</div>
      <div class="stat-label">Brand dalam<br>1 platform</div>
    </div>
    <div class="stat-item">
      <div class="stat-num">84</div>
      <div class="stat-label">Automated tests<br>tanpa bug</div>
    </div>
    <div class="stat-item">
      <div class="stat-num">100%</div>
      <div class="stat-label">Mobile-first<br>iOS & Android</div>
    </div>
  </div>
</div>

<!-- FITUR -->
<section class="features" id="fitur">
  <div class="section-inner">
    <div class="features-header">
      <div class="section-eyebrow">Fitur Lengkap</div>
      <h2 class="section-title">Semua yang Anda butuhkan,<br>dalam satu platform</h2>
      <p class="section-desc">Dirancang khusus untuk operasional F&B Indonesia — dari warung kopi hingga restoran chain besar.</p>
    </div>
    <div class="features-grid">
      <div class="feat-card">
        <div class="feat-icon">
          <svg viewBox="0 0 24 24"><path d="M20 7H4C2.9 7 2 7.9 2 9v11c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V9c0-1.1-.9-2-2-2z"/><path d="M16 3H8L6 7h12l-2-4z"/><line x1="12" y1="12" x2="12" y2="16"/><line x1="10" y1="14" x2="14" y2="14"/></svg>
        </div>
        <h3 class="feat-title">Open Stock & Opname Harian</h3>
        <p class="feat-desc">Input stok awal batch multi-item sekaligus. Opname harian dengan pemisahan satuan utuh dan ecer — cocok untuk operasional bar dan kitchen.</p>
      </div>
      <div class="feat-card">
        <div class="feat-icon">
          <svg viewBox="0 0 24 24"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
        </div>
        <h3 class="feat-title">Penerimaan Barang 4 Sumber</h3>
        <p class="feat-desc">Terima barang dari roastery, central kitchen, purchasing, dan supplier luar — semua dalam satu alur dengan approval workflow dan foto dokumen.</p>
      </div>
      <div class="feat-card">
        <div class="feat-icon">
          <svg viewBox="0 0 24 24"><path d="M3 3h18v4H3z"/><path d="M3 10h18v4H3z"/><path d="M3 17h18v4H3z"/></svg>
        </div>
        <h3 class="feat-title">Spoil & Waste Tracking</h3>
        <p class="feat-desc">Catat pemborosan dengan foto bukti. Sistem otomatis deteksi foto duplikat untuk mencegah kecurangan. Alert langsung ke PIC jika ada anomali.</p>
      </div>
      <div class="feat-card">
        <div class="feat-icon">
          <svg viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
        </div>
        <h3 class="feat-title">Laporan Real-Time</h3>
        <p class="feat-desc">Laporan mutasi stok, spoil, dan penerimaan barang dengan filter per outlet, per brand, per periode. Export Excel satu klik.</p>
      </div>
      <div class="feat-card">
        <div class="feat-icon">
          <svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
        </div>
        <h3 class="feat-title">Multi-Outlet, Multi-Brand</h3>
        <p class="feat-desc">Kelola semua outlet dalam satu akun. Manager area pantau gerai di wilayahnya. Pimpinan lihat ringkasan seluruh brand sekaligus.</p>
      </div>
      <div class="feat-card">
        <div class="feat-icon">
          <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        </div>
        <h3 class="feat-title">Import & Export Excel</h3>
        <p class="feat-desc">Upload data master item, satuan, dan stok awal via template Excel. Download laporan kapan saja. Tidak perlu input satu-satu dari nol.</p>
      </div>
    </div>
  </div>
</section>

<!-- HOW IT WORKS -->
<section class="how" id="cara-kerja">
  <div class="section-inner">
    <div class="how-layout">
      <div>
        <div class="section-eyebrow">Cara Kerja</div>
        <h2 class="section-title">Mulai dalam<br>10 menit, bukan<br>10 minggu</h2>
        <p class="section-desc" style="margin-bottom:36px">Tidak perlu implementasi berbulan-bulan. Setup master data, tambah user, dan langsung operasional.</p>
        <div class="how-steps">
          <div class="how-step active">
            <div class="how-step-num">1</div>
            <div class="how-step-content">
              <div class="how-step-title">Setup Master Data</div>
              <div class="how-step-desc">Import item bahan baku via Excel template. Atur satuan, konversi, kategori, dan distribusi ke outlet — selesai dalam hitungan menit.</div>
            </div>
          </div>
          <div class="how-step">
            <div class="how-step-num">2</div>
            <div class="how-step-content">
              <div class="how-step-title">Input Open Stock Awal</div>
              <div class="how-step-desc">Staff input stok awal per outlet. Sistem langsung mencatat ke ledger stok yang immutable dan akurat.</div>
            </div>
          </div>
          <div class="how-step">
            <div class="how-step-num">3</div>
            <div class="how-step-content">
              <div class="how-step-title">Operasional Harian</div>
              <div class="how-step-desc">Setiap hari: terima barang, catat spoil, opname stok. Semua dari HP, bahkan offline sekalipun.</div>
            </div>
          </div>
          <div class="how-step">
            <div class="how-step-num">4</div>
            <div class="how-step-content">
              <div class="how-step-title">Analisis & Keputusan</div>
              <div class="how-step-desc">Laporan otomatis tersedia setiap saat. Pimpinan bisa pantau semua outlet dari mana saja tanpa harus WhatsApp staff dulu.</div>
            </div>
          </div>
        </div>
      </div>
      <div class="how-visual">
        <div class="how-screen active" style="text-align:center;padding:20px">
          <div style="width:64px;height:64px;background:var(--green-50);border-radius:20px;display:flex;align-items:center;justify-content:center;margin:0 auto 20px">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#40916C" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
          </div>
          <div style="font-family:var(--font-head);font-size:20px;font-weight:700;color:var(--gray-900);margin-bottom:10px">Template Excel Siap</div>
          <div style="font-size:14px;color:var(--gray-500);line-height:1.6;margin-bottom:24px">Download template, isi data item, upload — dan semua master data langsung masuk ke sistem.</div>
          <div style="background:var(--green-50);border-radius:14px;padding:20px;text-align:left">
            <div style="font-size:12px;font-weight:600;color:var(--green-700);margin-bottom:12px;text-transform:uppercase;letter-spacing:0.5px">Template tersedia untuk:</div>
            <div style="display:flex;flex-direction:column;gap:8px">
              <div style="display:flex;align-items:center;gap:8px;font-size:13px;color:var(--gray-700)">
                <div style="width:6px;height:6px;border-radius:50%;background:var(--green-500)"></div>
                Data Item & Bahan Baku
              </div>
              <div style="display:flex;align-items:center;gap:8px;font-size:13px;color:var(--gray-700)">
                <div style="width:6px;height:6px;border-radius:50%;background:var(--green-500)"></div>
                Satuan & Konversi Satuan
              </div>
              <div style="display:flex;align-items:center;gap:8px;font-size:13px;color:var(--gray-700)">
                <div style="width:6px;height:6px;border-radius:50%;background:var(--green-500)"></div>
                Open Stock Awal (batch)
              </div>
              <div style="display:flex;align-items:center;gap:8px;font-size:13px;color:var(--gray-700)">
                <div style="width:6px;height:6px;border-radius:50%;background:var(--green-500)"></div>
                Mapping Item ke Outlet
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- MOBILE -->
<section class="mobile">
  <div class="mobile-inner">
    <div>
      <div class="section-eyebrow">Mobile-First</div>
      <h2 class="section-title">Dirancang untuk<br>tim operasional<br>di lapangan</h2>
      <p class="section-desc">Staff bar dan kitchen tidak pakai laptop. SIFOBI dibuat khusus untuk layar HP — iOS dan Android — dengan UX yang intuitif bahkan tanpa pelatihan panjang.</p>
      <div class="mobile-features">
        <div class="mobile-feat">
          <div class="mobile-feat-icon">
            <svg viewBox="0 0 24 24"><rect x="5" y="2" width="14" height="20" rx="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>
          </div>
          <div>
            <div class="mobile-feat-title">Tap-friendly, layar kecil</div>
            <div class="mobile-feat-desc">Tombol minimal 44px, font 16px untuk cegah zoom di Safari iOS. Nyaman dipakai jari basah sekalipun.</div>
          </div>
        </div>
        <div class="mobile-feat">
          <div class="mobile-feat-icon">
            <svg viewBox="0 0 24 24"><line x1="1" y1="1" x2="23" y2="23"/><path d="M16.72 11.06A10.94 10.94 0 0119 12.55"/><path d="M5 12.55a10.94 10.94 0 015.17-2.39"/><path d="M10.71 5.05A16 16 0 0122.56 9"/><path d="M1.42 9a15.91 15.91 0 014.7-2.88"/><path d="M8.53 16.11a6 6 0 016.95 0"/><line x1="12" y1="20" x2="12.01" y2="20"/></svg>
          </div>
          <div>
            <div class="mobile-feat-title">Opname bisa offline</div>
            <div class="mobile-feat-desc">Sinyal buruk? Data tersimpan di perangkat dan otomatis sync saat online kembali. Tidak ada data yang hilang.</div>
          </div>
        </div>
        <div class="mobile-feat">
          <div class="mobile-feat-icon">
            <svg viewBox="0 0 24 24"><path d="M23 19a2 2 0 01-2 2H3a2 2 0 01-2-2V8a2 2 0 012-2h4l2-3h6l2 3h4a2 2 0 012 2z"/><circle cx="12" cy="13" r="4"/></svg>
          </div>
          <div>
            <div class="mobile-feat-title">Foto langsung dari kamera</div>
            <div class="mobile-feat-desc">Bukti spoil, foto dokumen penerimaan — cukup tap dan foto langsung dari kamera HP. Tidak perlu pindah ke aplikasi lain.</div>
          </div>
        </div>
      </div>
    </div>
    <div class="phone-mockup">
      <div class="phone-frame">
        <div class="phone-notch"></div>
        <div class="phone-screen">
          <div class="phone-topbar">
            <div class="phone-greeting">Selamat pagi,</div>
            <div class="phone-name">Budi Santoso</div>
            <div class="phone-outlet"><svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="vertical-align:middle"><path d="M21 10c0 7-9 13-9 13S3 17 3 10a9 9 0 1118 0z"/><circle cx="12" cy="10" r="3"/></svg> MKO Grand City</div>
          </div>
          <div class="phone-kpis">
            <div class="phone-kpi">
              <div class="phone-kpi-label">Item Aktif</div>
              <div class="phone-kpi-val">47</div>
            </div>
            <div class="phone-kpi">
              <div class="phone-kpi-label">Spoil Hari Ini</div>
              <div class="phone-kpi-val" style="color:#dc2626">2</div>
            </div>
            <div class="phone-kpi">
              <div class="phone-kpi-label">Stok Menipis</div>
              <div class="phone-kpi-val" style="color:#d97706">3</div>
            </div>
            <div class="phone-kpi">
              <div class="phone-kpi-label">Opname</div>
              <div class="phone-kpi-val" style="color:#16a34a"><svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="vertical-align:middle"><path d="M20 6L9 17l-5-5"/></svg></div>
            </div>
          </div>
          <div class="phone-section">
            <div class="phone-section-title">Stok Terkini</div>
            <div class="phone-item">
              <div class="phone-item-dot" style="background:#16a34a"></div>
              <span class="phone-item-name">Susu UHT</span>
              <span class="phone-item-qty">24 karton</span>
            </div>
            <div class="phone-item">
              <div class="phone-item-dot" style="background:#16a34a"></div>
              <span class="phone-item-name">Espresso Blend</span>
              <span class="phone-item-qty">8.5 kg</span>
            </div>
            <div class="phone-item">
              <div class="phone-item-dot" style="background:#d97706"></div>
              <span class="phone-item-name">Sirup Hazelnut</span>
              <span class="phone-item-qty">2 botol <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="vertical-align:middle"><path d="M12 9v4"/><path d="M12 17h.01"/><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg></span>
            </div>
          </div>
          <div class="phone-nav">
            <div class="phone-nav-item">
              <div class="phone-nav-icon active"></div>
              <div class="phone-nav-label active">Beranda</div>
            </div>
            <div class="phone-nav-item">
              <div class="phone-nav-icon"></div>
              <div class="phone-nav-label">Stok</div>
            </div>
            <div class="phone-nav-item">
              <div class="phone-nav-icon"></div>
              <div class="phone-nav-label">Opname</div>
            </div>
            <div class="phone-nav-item">
              <div class="phone-nav-icon"></div>
              <div class="phone-nav-label">Spoil</div>
            </div>
            <div class="phone-nav-item">
              <div class="phone-nav-icon"></div>
              <div class="phone-nav-label">Profil</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- PRICING -->
<section class="pricing" id="harga">
  <div class="section-inner">
    <div class="pricing-header">
      <div class="section-eyebrow">Harga Transparan</div>
      <h2 class="section-title">Flat monthly, tidak ada biaya tersembunyi</h2>
      <p class="section-desc">Pilih paket sesuai skala bisnis Anda. Semua paket sudah termasuk transaksi tidak terbatas.</p>
    </div>
    <div class="pricing-grid">
      <div class="price-card">
        <div class="price-name">Starter</div>
        <div class="price-desc">Cocok untuk warung kopi, kafe, atau restoran 1 outlet</div>
        <div class="price-amount">
          <span class="price-currency">Rp</span><span class="price-num">99</span><span style="font-size:16px;color:var(--gray-400)">rb</span>
        </div>
        <div class="price-period">per bulan</div>
        <div class="price-per">1 outlet · tagihan bulanan</div>
        <ul class="price-features">
          <li><div class="price-check"><svg viewBox="0 0 12 12"><polyline points="2 6 5 9 10 3"/></svg></div>Stok & Opname Harian</li>
          <li><div class="price-check"><svg viewBox="0 0 12 12"><polyline points="2 6 5 9 10 3"/></svg></div>Penerimaan Barang 4 sumber</li>
          <li><div class="price-check"><svg viewBox="0 0 12 12"><polyline points="2 6 5 9 10 3"/></svg></div>Spoil & Waste Tracking</li>
          <li><div class="price-check"><svg viewBox="0 0 12 12"><polyline points="2 6 5 9 10 3"/></svg></div>Laporan & Export Excel</li>
          <li><div class="price-check"><svg viewBox="0 0 12 12"><polyline points="2 6 5 9 10 3"/></svg></div>5 user (1 PIC + 4 Staff)</li>
          <li><div class="price-check"><svg viewBox="0 0 12 12"><polyline points="2 6 5 9 10 3"/></svg></div>Support via WhatsApp</li>
        </ul>
        <a href="{{ route('register') }}" class="price-btn price-btn-outline">Mulai Coba Gratis</a>
      </div>
      <div class="price-card featured">
        <div class="price-popular">Paling Populer</div>
        <div class="price-name">Growth</div>
        <div class="price-desc">Untuk chain F&B yang sedang berkembang</div>
        <div class="price-amount">
          <span class="price-currency">Rp</span><span class="price-num">249</span><span style="font-size:16px;color:var(--gray-400)">rb</span>
        </div>
        <div class="price-period">per bulan</div>
        <div class="price-per">hingga 5 outlet · hemat 50% vs Starter</div>
        <ul class="price-features">
          <li><div class="price-check"><svg viewBox="0 0 12 12"><polyline points="2 6 5 9 10 3"/></svg></div>Semua fitur Starter</li>
          <li><div class="price-check"><svg viewBox="0 0 12 12"><polyline points="2 6 5 9 10 3"/></svg></div>Hingga 5 outlet sekaligus</li>
          <li><div class="price-check"><svg viewBox="0 0 12 12"><polyline points="2 6 5 9 10 3"/></svg></div>Multi-brand tidak terbatas</li>
          <li><div class="price-check"><svg viewBox="0 0 12 12"><polyline points="2 6 5 9 10 3"/></svg></div>Dashboard Manager Area</li>
          <li><div class="price-check"><svg viewBox="0 0 12 12"><polyline points="2 6 5 9 10 3"/></svg></div>User tidak terbatas</li>
          <li><div class="price-check"><svg viewBox="0 0 12 12"><polyline points="2 6 5 9 10 3"/></svg></div>Laporan konsolidasi semua outlet</li>
          <li><div class="price-check"><svg viewBox="0 0 12 12"><polyline points="2 6 5 9 10 3"/></svg></div>Integrasi sistem eksternal (API)</li>
        </ul>
        <a href="{{ route('register') }}" class="price-btn price-btn-solid">Mulai Coba Gratis</a>
      </div>
      <div class="price-card">
        <div class="price-name">Enterprise</div>
        <div class="price-desc">Untuk grup F&B dengan 6+ outlet atau kebutuhan khusus</div>
        <div class="price-amount">
          <span style="font-family:var(--font-head);font-size:36px;font-weight:800;color:var(--gray-900)">Custom</span>
        </div>
        <div class="price-period">Negosiasi sesuai skala</div>
        <div class="price-per">Hubungi tim kami untuk penawaran</div>
        <ul class="price-features">
          <li><div class="price-check"><svg viewBox="0 0 12 12"><polyline points="2 6 5 9 10 3"/></svg></div>Semua fitur Growth</li>
          <li><div class="price-check"><svg viewBox="0 0 12 12"><polyline points="2 6 5 9 10 3"/></svg></div>Outlet tidak terbatas</li>
          <li><div class="price-check"><svg viewBox="0 0 12 12"><polyline points="2 6 5 9 10 3"/></svg></div>Database dedicated (isolasi penuh)</li>
          <li><div class="price-check"><svg viewBox="0 0 12 12"><polyline points="2 6 5 9 10 3"/></svg></div>Custom integrasi POS & delivery</li>
          <li><div class="price-check"><svg viewBox="0 0 12 12"><polyline points="2 6 5 9 10 3"/></svg></div>SLA & dukungan prioritas 24/7</div></li>
          <li><div class="price-check"><svg viewBox="0 0 12 12"><polyline points="2 6 5 9 10 3"/></svg></div>Training & onboarding onsite</li>
        </ul>
        <a href="mailto:hello@sifobi.id" class="price-btn price-btn-outline">Hubungi Kami</a>
      </div>
    </div>
    <p style="text-align:center;color:var(--gray-500);font-size:14px;margin-top:32px;line-height:2">✓ Coba gratis 14 hari — tidak perlu kartu kredit<br>✓ Bisa upgrade atau downgrade kapan saja<br>✓ Data Anda tetap aman jika berhenti berlangganan</p>
  </div>
</section>

<!-- TESTIMONI -->
<section class="testimonial" id="testimoni">
  <div class="testi-inner">
    <div class="testi-header">
      <div class="section-eyebrow">Testimoni</div>
      <h2 class="section-title">Dipercaya operator F&B Indonesia</h2>
    </div>
    <div class="testi-grid">
      <div class="testi-card">
        <div class="testi-stars">
          <span class="star">★</span><span class="star">★</span><span class="star">★</span><span class="star">★</span><span class="star">★</span>
        </div>
        <p class="testi-quote">"Sekarang PIC outlet bisa approve penerimaan barang dari HP, tidak perlu lagi nunggu laporan WhatsApp dari staff. Data langsung masuk ke sistem."</p>
        <div class="testi-person">
          <div class="testi-avatar">AR</div>
          <div>
            <div class="testi-name">Andi Rachmat</div>
            <div class="testi-role">Manager Area · My Kopi-O! Jawa Timur</div>
          </div>
        </div>
      </div>
      <div class="testi-card">
        <div class="testi-stars">
          <span class="star">★</span><span class="star">★</span><span class="star">★</span><span class="star">★</span><span class="star">★</span>
        </div>
        <p class="testi-quote">"Import data dari Excel sangat membantu. Dalam 1 jam semua master item 200+ sudah masuk. Dulu kalau manual bisa 2 hari sendiri."</p>
        <div class="testi-person">
          <div class="testi-avatar">DW</div>
          <div>
            <div class="testi-name">Dewi Wulandari</div>
            <div class="testi-role">Finance Staff · Quali Group</div>
          </div>
        </div>
      </div>
      <div class="testi-card">
        <div class="testi-stars">
          <span class="star">★</span><span class="star">★</span><span class="star">★</span><span class="star">★</span><span class="star">★</span>
        </div>
        <p class="testi-quote">"Staff bar kami yang tidak terbiasa sistem mudah sekali belajarnya. Opname harian sekarang selesai 10 menit, dulu bisa 45 menit pakai kertas."</p>
        <div class="testi-person">
          <div class="testi-avatar">BS</div>
          <div>
            <div class="testi-name">Bimo Suryo</div>
            <div class="testi-role">PIC Outlet · Kampong Melayu Grand City</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- CTA -->
<section class="cta-section">
  <div class="cta-inner">
    <h2>Siap kendalikan stok F&B Anda?</h2>
    <p>Coba SIFOBI gratis 14 hari — tanpa kartu kredit, tanpa kontrak. Setup dalam 10 menit.</p>
    <div class="cta-actions">
      <a href="{{ route('register') }}" class="btn-primary btn-primary-lg">Mulai Gratis Sekarang</a>
      <a href="mailto:hello@sifobi.id" class="btn-outline-lg">Hubungi Tim Kami</a>
    </div>
    <p class="cta-note">Tidak memerlukan kartu kredit · Cancel kapan saja · Data Anda aman</p>
  </div>
</section>

<!-- FOOTER -->
<footer>
  <div class="footer-inner">
    <div class="footer-top">
      <div>
        <div class="footer-brand-name">SIFOBI</div>
        <div class="footer-brand-desc">Platform inventori modern untuk bisnis Food & Beverage Indonesia. Multi-outlet, multi-brand, mobile-first.</div>
      </div>
      <div>
        <div class="footer-col-title">Produk</div>
        <ul class="footer-links">
          <li><a href="#fitur">Fitur</a></li>
          <li><a href="#harga">Harga</a></li>
          <li><a href="#">Changelog</a></li>
          <li><a href="#">Roadmap</a></li>
        </ul>
      </div>
      <div>
        <div class="footer-col-title">Perusahaan</div>
        <ul class="footer-links">
          <li><a href="#">Tentang Kami</a></li>
          <li><a href="#">Blog</a></li>
          <li><a href="#">Karir</a></li>
          <li><a href="#">Kontak</a></li>
        </ul>
      </div>
      <div>
        <div class="footer-col-title">Legal</div>
        <ul class="footer-links">
          <li><a href="#">Syarat Layanan</a></li>
          <li><a href="#">Kebijakan Privasi</a></li>
          <li><a href="#">Keamanan Data</a></li>
        </ul>
      </div>
    </div>
    <div class="footer-bottom">
      <span class="footer-copy">© 2026 SIFOBI. Hak cipta dilindungi.</span>
      <span style="color:rgba(255,255,255,0.25);font-size:12px">Dibuat dengan ❤️ untuk F&B Indonesia</span>
    </div>
  </div>
</footer>

<script>
const steps = document.querySelectorAll('.how-step');
steps.forEach(step => {
  step.addEventListener('click', () => {
    steps.forEach(s => s.classList.remove('active'));
    step.classList.add('active');
  });
});
</script>
</body>
</html>
