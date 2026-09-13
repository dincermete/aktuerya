(function () {
  "use strict";

  const $ = (id) => document.getElementById(id);
  const frm = $("frm");
  const HAK = JSON.parse($("hakTanim").textContent);
  const API = "api.php";
  const DEPO = "destekTazminat.form.v1";

  const nf2 = new Intl.NumberFormat("tr-TR", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  const nf0 = new Intl.NumberFormat("tr-TR", { maximumFractionDigits: 0 });
  const tl = (x) => nf2.format(x || 0) + " TL";
  const tl0 = (x) => nf0.format(Math.round(x || 0)) + " TL";
  const kisa = (x) => (Number.isInteger(x) ? String(x) : String(+Number(x).toFixed(2)).replace(".", ","));
  const esc = (s) => String(s).replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));
  const yilAy = (v) => {
    if (!(v > 0)) return "0 yıl 0 ay";
    let y = Math.floor(v + 1e-9), m = Math.round((v - y) * 12);
    if (m >= 12) { y++; m = 0; }
    return y + " yıl " + m + " ay";
  };

  /* ---------- form <-> nesne ---------- */
  function topla() {
    const o = { hakSahipleri: {} };
    frm.querySelectorAll("[data-k]").forEach((el) => {
      if (el.type === "radio") { if (el.checked) o[el.dataset.k] = el.value; }
      else if (el.type === "checkbox") o[el.dataset.k] = el.checked;
      else o[el.dataset.k] = el.value;
    });
    HAK.forEach((h) => {
      const r = {};
      frm.querySelectorAll(`[data-h="${h.anahtar}"]`).forEach((el) => { r[el.dataset.f] = el.type === "checkbox" ? el.checked : el.value; });
      o.hakSahipleri[h.anahtar] = r;
    });
    return o;
  }

  function uygula(s) {
    frm.querySelectorAll("[data-k]").forEach((el) => {
      const k = el.dataset.k;
      if (!(k in s)) return;
      if (el.type === "radio") el.checked = s[k] === el.value;
      else if (el.type === "checkbox") el.checked = !!s[k];
      else el.value = s[k];
    });
    HAK.forEach((h) => {
      const r = (s.hakSahipleri || {})[h.anahtar];
      if (!r) return;
      frm.querySelectorAll(`[data-h="${h.anahtar}"]`).forEach((el) => {
        const f = el.dataset.f;
        if (!(f in r)) return;
        if (el.type === "checkbox") el.checked = !!r[f]; else el.value = r[f];
      });
    });
  }

  const VARSAYILAN = topla();
  const ORNEK = {
    kazaliAdi: "Örnek Kazalı", kusur: "80", olayTarihi: "2023-03-14", dogumTarihi: "1989-06-12", cinsiyet: "E", ucret: "0",
    mod: "destek", kazaTuru: "is", yontem: "progresif", tablo: "trh", paylastirma: "21",
    hakSahipleri: {
      es: { hesapla: true, ad: "Eş", dogumTarihi: "1992-02-03", cinsiyet: "K" },
      c1: { hesapla: true, ad: "Kız çocuk", dogumTarihi: "2015-09-01", cinsiyet: "K" },
      c2: { hesapla: true, ad: "Oğul", dogumTarihi: "2019-05-20", cinsiyet: "E", yuksekOgrenim: true },
      ana: { hesapla: true, ad: "Anne", dogumTarihi: "1963-04-10" },
      baba: { hesapla: true, ad: "Baba", dogumTarihi: "1960-11-02" },
    },
  };

  /* ---------- hesap ---------- */
  let ornekAktif = false, zamanlayici = null, istekNo = 0;

  function modGuncelle() {
    const destek = frm.querySelector('[data-k="mod"]:checked').value === "destek";
    document.querySelectorAll("[data-destek]").forEach((el) => (el.hidden = !destek));
    document.querySelectorAll("[data-isgucu]").forEach((el) => (el.hidden = destek));
  }

  async function hesapla() {
    modGuncelle();
    const no = ++istekNo;
    document.querySelector(".sheet").classList.add("busy");
    try {
      const yanit = await fetch(API, { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify(topla()), credentials: "same-origin" });
      if (yanit.status === 401) {
        location.href = "giris.php";
        return;
      }
      const sonuc = await yanit.json();
      if (no === istekNo) ciz(sonuc);
    } catch (e) {
      if (no === istekNo) $("repBody").innerHTML = `<div class="err">Sunucuya ulaşılamadı: ${esc(e.message)}. DDEV projesinin çalıştığını kontrol edin (ddev start).</div>`;
    } finally {
      if (no === istekNo) document.querySelector(".sheet").classList.remove("busy");
    }
  }

  function kaydet() { try { localStorage.setItem(DEPO, JSON.stringify(topla())); } catch (e) { /* depolama kapalı */ } }
  function planla() { ornekAktif = false; clearTimeout(zamanlayici); zamanlayici = setTimeout(() => { hesapla(); kaydet(); }, 250); }

  /* ---------- çizim ---------- */
  function ciz(s) {
    const body = $("repBody");
    if (s.hata) {
      body.innerHTML = `<div class="err">${esc(s.hata)}</div>`;
      ["tblShare", "tblG", "tblA", "tblP"].forEach((id) => ($(id).innerHTML = ""));
      return;
    }
    const destek = s.mod === "destek", p = s.parametre, oz = s.ozet;
    $("repTitle").textContent = destek ? "Destek tazminatı" : "İş gücü kaybı";
    $("repDate").textContent = "Rapor: " + oz.raporTarihi;

    const kutu = (t, v, alt) => `<div><dt>${t}</dt><dd>${v}${alt ? `<small>${alt}</small>` : ""}</dd></div>`;
    const ay = (m) => Math.floor(m / 12) + " yıl " + (m % 12) + " ay";
    const sa = oz.sureAy;
    let h = `<dl class="periods">
      ${kutu("Olay tarihi yaş", ay(sa.olayYasi), "doğum " + oz.dogumTarihi)}
      ${kutu("Bakiye ömür", ay(sa.bakiyeOmur), "ömür sonu " + oz.omurSonu)}
      ${kutu("Faal çalışma", ay(sa.faalCalisma), "")}
      ${kutu("Geçmiş dönem", ay(sa.gecmisDonem), oz.gecmisBas + " – " + oz.gecmisSon)}
      ${kutu("Gelecek aktif", ay(sa.aktifDonem), oz.aktifDonem > 0 ? "→ " + oz.aktifSon : "—")}
      ${kutu("Gelecek pasif", ay(sa.pasifDonem), "→ " + oz.pasifSon)}
    </dl>
    <div class="total"><div><div class="lab">Genel toplam${ornekAktif ? ' &nbsp;<span class="badge">Örnek veriler</span>' : ""}</div>
      <div class="hint" style="margin-top:4px">Davalı kusuru %${kisa(p.kusur)} · ${p.yontem === "aktuer" ? "Aktüeryal" : "Progresif rant"} · ${p.tablo === "pmf" ? "PMF-1931" : "TRH-2010"}</div></div>
      <span class="big">${tl(s.toplam)}</span></div>`;
    if (s.faiz && s.faiz.tutar > 0) {
      h += `<div class="total" style="padding-top:10px;padding-bottom:10px"><div class="lab">İşlemiş ${s.faiz.tur === "avans" ? "avans" : "yasal"} faiz
        <div class="hint" style="margin-top:4px">${s.faiz.bas} – ${s.faiz.son}</div></div><span class="mono" style="font-size:18px">${tl(s.faiz.tutar)}</span></div>`;
    }

    if (destek && !s.sonuclar.length) {
      h += `<div class="err">Hak sahipleri tablosunda en az bir kişiyi işaretleyip doğum tarihini girin.</div>`;
    } else {
      h += `<div class="scroll"><table class="res"><thead><tr><th>Kişi</th><th>Geçmiş</th><th>Aktif</th><th>Pasif</th><th>${destek ? "İndirim" : "GİG + Bakıcı"}</th><th>Toplam</th></tr></thead><tbody>`;
      s.sonuclar.forEach((r) => {
        const ind = destek ? -(r.evlenmeIndirimi + r.psd + (r.yetistirme || 0)) : r.geciciIsgoremezlik + r.bakici - r.psd;
        const alt = destek
          ? [r.evlenmeIndirimi ? "evl. " + tl0(r.evlenmeIndirimi) : "", r.psd ? "PSD " + tl0(r.psd) : "", r.yetistirme ? "yetiştirme " + tl0(r.yetistirme) : ""].filter(Boolean).join(" · ")
          : r.psd ? "PSD −" + tl0(r.psd) : "";
        h += `<tr><td>${esc(r.ad)}<span class="sub">${destek ? kisa(+r.destekSuresi.toFixed(1)) + " yıl destek" : "%" + kisa(p.isgucuOrani) + " oran"}</span></td>
          <td>${tl0(r.gecmis)}</td><td>${tl0(r.aktif)}</td><td>${tl0(r.pasif)}</td>
          <td class="${ind < 0 ? "neg" : ""}">${ind ? (ind < 0 ? "−" : "") + tl0(Math.abs(ind)) : "—"}${alt ? `<span class="sub">${alt}</span>` : ""}</td>
          <td class="tot">${tl0(r.toplam)}</td></tr>`;
      });
      const T = (k) => s.sonuclar.reduce((a, r) => a + r[k], 0);
      h += `</tbody><tfoot><tr><td>Toplam</td><td>${tl0(T("gecmis"))}</td><td>${tl0(T("aktif"))}</td><td>${tl0(T("pasif"))}</td><td></td><td>${tl0(s.toplam)}</td></tr></tfoot></table></div>`;
    }
    h += `<ul class="notes">${s.notlar.map((n) => `<li>${esc(n)}</li>`).join("")}
      <li>Aktif dönem aylık gelir ${tl(p.aktifAylik)} · pasif dönem ${tl(p.pasifAylik)} · artış %${kisa(p.artis)} / iskonto %${kisa(p.iskonto)}.</li></ul>`;
    body.innerHTML = h;

    s.hakSahipleri.forEach((r) => {
      $(r.anahtar + "_who").textContent = r.etiket;
      $(r.anahtar + "_tr").classList.toggle("off", !r.hesaplaniyor);
      $(r.anahtar + "_info").textContent = r.yas === null ? "—" : `${r.yas} yaş · ${r.hesaplaniyor ? r.destekYili + " yıl" : "hesaplanmıyor"}`;
    });

    tablolar(s, destek);
  }

  function tablolar(s, destek) {
    const kol = s.kolonlar, K = s.parametre.kusur / 100;
    const basliklar = kol.map((k) => `<th>${esc(k.ad)}</th>`).join("");
    const hucreler = (satir) => kol.map((k) => {
      const x = satir.kisiler[k.id];
      return `<td>${x && x.tutar ? `<span class="fr">${esc(x.etiket)}</span>` : ""}${tl0(x ? x.tutar : 0)}</td>`;
    }).join("");
    const topl = (satirlar) => kol.map((k) => satirlar.reduce((a, r) => a + (r.kisiler[k.id] ? r.kisiler[k.id].tutar : 0), 0));
    const kusurSatiri = (t, span) => `<tr class="kus"><td class="l" colspan="${span}">Davalı kusuru (%${kisa(s.parametre.kusur)})</td>${t.map((v) => `<td>${tl0(v * K)}</td>`).join("")}</tr>`;

    if (destek && s.paylasim.length) {
      $("tblShare").innerHTML = `<table class="tbl"><thead><tr><th class="l">Dönem</th><th>Süre</th>${basliklar}</tr></thead><tbody>${
        s.paylasim.map((d) => `<tr><td class="l">${d.bas} – ${d.son}</td><td>${yilAy(d.sure)}</td>${kol.map((k) => `<td>${d.paylar[k.id] ? esc(d.paylar[k.id]) : "—"}</td>`).join("")}</tr>`).join("")
      }</tbody></table>`;
    } else {
      $("tblShare").innerHTML = `<p class="caveat">${destek ? "Paylaşım için hak sahibi seçin." : "İş gücü kaybında paylaştırma yapılmaz; tüm zarar kazalıya aittir."}</p>`;
    }

    const G = s.tablolar.gecmis;
    if (G.length) {
      const t = topl(G), gelir = G.reduce((a, r) => a + r.gelir, 0);
      $("tblG").innerHTML = `<table class="tbl"><thead><tr><th class="l">Dönem</th><th>Gün</th><th>Günlük ücret</th><th>Dönem gelir</th>${basliklar}</tr></thead><tbody>${
        G.map((r) => `<tr><td class="l">${r.bas} – ${r.son}</td><td>${r.gun}</td><td>${tl(r.gunluk)}</td><td>${tl0(r.gelir)}</td>${hucreler(r)}</tr>`).join("")
      }<tr class="sum"><td class="l" colspan="3">Toplam</td><td>${tl0(gelir)}</td>${t.map((v) => `<td>${tl0(v)}</td>`).join("")}</tr>${kusurSatiri(t, 4)}</tbody></table>`;
      $("sumG").textContent = tl0(s.kazaliGeliri.gecmis) + " (kusur karşılığı)";
    } else { $("tblG").innerHTML = `<p class="caveat">Bu dönem yok.</p>`; $("sumG").textContent = ""; }

    [["tblA", "sumA", s.tablolar.aktif, "aktif"], ["tblP", "sumP", s.tablolar.pasif, "pasif"]].forEach(([id, ozId, satirlar, ad]) => {
      if (!satirlar.length) { $(id).innerHTML = `<p class="caveat">Bu dönem yok.</p>`; $(ozId).textContent = ""; return; }
      const t = topl(satirlar), gelir = satirlar.reduce((a, r) => a + r.gelir, 0);
      let h = `<table class="tbl"><thead><tr><th class="l">Yıl</th><th class="l">Dönem sonu</th><th>Yaş</th><th>Artışlı yıllık gelir</th><th>Kn</th><th>Dönem gelir</th>${basliklar}</tr></thead><tbody>`;
      h += satirlar.map((r) => `<tr><td class="l">${r.n}</td><td class="l">${r.son}${r.ay < 12 ? ` <span class="fr">(${r.ay} ay)</span>` : ""}</td><td>${kisa(r.yas)}</td><td>${tl0(r.artisliYillik)}</td><td>${r.kn.toFixed(4).replace(".", ",")}</td><td>${tl0(r.gelir)}</td>${hucreler(r)}</tr>`).join("");
      h += `<tr class="sum"><td class="l" colspan="5">Toplam</td><td>${tl0(gelir)}</td>${t.map((v) => `<td>${tl0(v)}</td>`).join("")}</tr>${kusurSatiri(t, 6)}`;
      if (destek && s.evlenme && s.evlenme.oran) {
        h += `<tr><td class="l" colspan="6">Eş için evlenme ihtimali indirimi (%${s.evlenme.oran})</td>${kol.map((k, i) => `<td class="neg">${k.tur === "es" ? "−" + tl0(t[i] * K * s.evlenme.oran / 100) : ""}</td>`).join("")}</tr>`;
      }
      $(id).innerHTML = h + "</tbody></table>";
      $(ozId).textContent = tl0(s.kazaliGeliri[ad]) + " (kusur karşılığı)";
    });
  }

  /* ---------- olaylar ---------- */
  frm.addEventListener("input", planla);
  frm.addEventListener("change", planla);
  $("btnPrint").addEventListener("click", () => { document.querySelectorAll("details.block").forEach((d) => (d.open = true)); window.print(); });
  $("btnSample").addEventListener("click", () => { uygula(VARSAYILAN); uygula(ORNEK); ornekAktif = true; hesapla(); kaydet(); });
  $("btnReset").addEventListener("click", () => { uygula(VARSAYILAN); ornekAktif = false; hesapla(); kaydet(); });

  let kayitli = null;
  try { kayitli = JSON.parse(localStorage.getItem(DEPO) || "null"); } catch (e) { kayitli = null; }
  if (kayitli) { uygula(kayitli); } else { uygula(ORNEK); ornekAktif = true; }
  hesapla();
})();
