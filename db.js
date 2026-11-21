/* স্বাস্থ্যবাজার — শেয়ার্ড ডেটা লেয়ার
 * সাইট (index.html) ও অ্যাডমিন (admin.html) দুটোই এই ফাইল ব্যবহার করে।
 * ডেটা ব্রাউজারের localStorage-এ থাকে। লাইভ সার্ভারে নিতে চাইলে DB.load/DB.save
 * ফাংশন দুটো আপনার API (PHP/Node/Firebase) কল দিয়ে বদলে দিলেই হবে।
 */
(function () {
  const KEY = 'sb_db_v1';
  const uid = () => Date.now().toString(36) + Math.random().toString(36).slice(2, 7);
  const DAY = 86400000;

  function seed() {
    const categories = [
      ['medicine', 'ওষুধ', '💊'], ['beauty', 'বিউটি', '💄'], ['baby', 'মা ও শিশু', '👶'],
      ['food', 'খাদ্য ও পুষ্টি', '🥗'], ['devices', 'মেডিকেল ডিভাইস', '🩺'], ['skincare', 'স্কিনকেয়ার', '🧴'],
      ['haircare', 'হেয়ারকেয়ার', '💇'], ['herbal', 'হারবাল', '🌿'], ['supplement', 'সাপ্লিমেন্ট', '💪'],
      ['homecare', 'হোমকেয়ার', '🏠'], ['feminine', 'ফেমিনিন কেয়ার', '🌸'], ['pet', 'পেট কেয়ার', '🐾'],
      ['homeopathy', 'হোমিওপ্যাথি', '💧'], ['healthcare', 'হেলথকেয়ার', '❤️']
    ].map(([id, name, icon], i) => ({ id, name, icon, order: i + 1, active: true }));

    const P = (name, cat, icon, mrp, price, stock, sections, extra = {}) => ({
      id: uid(), name, cat, icon, img: '', mrp, price, stock, sections,
      brand: extra.brand || 'জেনেরিক', unit: extra.unit || '১ পিস', rx: !!extra.rx,
      desc: extra.desc || 'মানসম্মত ও অনুমোদিত পণ্য। ব্যবহারের আগে প্যাকেটের নির্দেশনা পড়ুন।',
      rating: +(4 + Math.random()).toFixed(1), reviews: Math.floor(Math.random() * 400) + 12,
      active: true, createdAt: Date.now()
    });
    const products = [
      P('প্যারাসিটামল ৫০০ মি.গ্রা.', 'medicine', '💊', 12, 10, 500, ['best'], { unit: '১০ ট্যাবলেট', brand: 'হেলথফার্মা' }),
      P('ওমিপ্রাজল ২০ মি.গ্রা.', 'medicine', '💊', 70, 63, 300, ['best'], { unit: '১০ ক্যাপসুল', rx: true }),
      P('সেট্রিজিন ১০ মি.গ্রা.', 'medicine', '💊', 35, 30, 400, [], { unit: '১০ ট্যাবলেট' }),
      P('ওরাল স্যালাইন', 'medicine', '💧', 120, 110, 250, ['best', 'daily'], { unit: '২০ প্যাক' }),
      P('ভিটামিন সি ২৫০ মি.গ্রা.', 'supplement', '🍊', 90, 80, 200, ['daily'], { unit: '৩০ ট্যাবলেট' }),
      P('মাল্টিভিটামিন', 'supplement', '💪', 650, 520, 80, ['best'], { unit: '৩০ ট্যাবলেট' }),
      P('ক্যালসিয়াম + ভিটামিন ডি', 'supplement', '🦴', 380, 330, 120, [], { unit: '৩০ ট্যাবলেট' }),
      P('হ্যান্ড স্যানিটাইজার ২০০ মি.লি.', 'homecare', '🧴', 150, 120, 150, ['daily'], {}),
      P('সার্জিক্যাল মাস্ক', 'healthcare', '😷', 300, 199, 300, ['best', 'daily'], { unit: '৫০ পিস' }),
      P('ব্যান্ডেজ স্ট্রিপ', 'healthcare', '🩹', 180, 150, 220, ['daily'], { unit: '১০০ পিস' }),
      P('ডিজিটাল থার্মোমিটার', 'devices', '🌡️', 450, 349, 60, ['best'], {}),
      P('ডিজিটাল ব্লাড প্রেশার মেশিন', 'devices', '🩺', 3500, 2790, 25, ['best'], { brand: 'কেয়ারটেক' }),
      P('গ্লুকোমিটার কিট', 'devices', '🩸', 2200, 1850, 30, [], {}),
      P('পালস অক্সিমিটার', 'devices', '📟', 1500, 1190, 4, [], {}),
      P('ময়েশ্চারাইজিং লোশন ৪০০ মি.লি.', 'skincare', '🧴', 650, 489, 90, ['winter'], {}),
      P('লিপ বাম', 'skincare', '💋', 250, 199, 140, ['winter'], { unit: '২ পিস' }),
      P('পেট্রোলিয়াম জেলি ১০০ গ্রা.', 'skincare', '🫙', 160, 135, 200, ['winter'], {}),
      P('অ্যালোভেরা জেল', 'skincare', '🌿', 420, 315, 70, ['winter'], {}),
      P('সানস্ক্রিন SPF 50', 'skincare', '☀️', 890, 699, 45, ['winter'], {}),
      P('ফেস ওয়াশ', 'beauty', '🫧', 350, 280, 100, ['winter'], {}),
      P('অ্যান্টি-ড্যানড্রাফ শ্যাম্পু', 'haircare', '🧴', 480, 399, 75, [], {}),
      P('হেয়ার অয়েল ২০০ মি.লি.', 'haircare', '🫒', 220, 190, 110, [], {}),
      P('ফিডিং বোতল ২৫০ মি.লি.', 'baby', '🍼', 550, 450, 60, ['baby'], {}),
      P('বেবি ডায়াপার (M)', 'baby', '👶', 1250, 999, 80, ['baby'], { unit: '৪০ পিস' }),
      P('বেবি লোশন', 'baby', '🧴', 380, 320, 70, ['baby'], {}),
      P('বেবি ওয়াইপস', 'baby', '🧻', 260, 210, 150, ['baby'], { unit: '৮০ পিস' }),
      P('বেবি সিরিয়াল ৪০০ গ্রা.', 'food', '🥣', 720, 649, 50, ['baby'], {}),
      P('ওটস ১ কেজি', 'food', '🌾', 550, 480, 65, ['daily'], {}),
      P('মধু ৫০০ গ্রা.', 'herbal', '🍯', 650, 560, 40, [], {}),
      P('স্যানিটারি ন্যাপকিন', 'feminine', '🌸', 180, 160, 200, ['daily'], { unit: '১০ পিস' }),
      P('পেট শ্যাম্পু', 'pet', '🐾', 600, 520, 20, [], {}),
      P('আর্নিকা মন্ট ৩০', 'homeopathy', '💧', 150, 140, 3, [], {})
    ];

    const sections = [
      { id: 'best', title: 'সেরা বাছাই', order: 1, active: true },
      { id: 'winter', title: 'শীতের স্কিনকেয়ার', order: 2, active: true },
      { id: 'daily', title: 'দৈনন্দিন প্রয়োজন', order: 3, active: true },
      { id: 'baby', title: 'মা ও শিশু', order: 4, active: true }
    ];

    const banners = [
      { id: uid(), title: 'ঘরে বসেই ওষুধ অর্ডার', sub: 'প্রেসক্রিপশন আপলোড করুন, বাকিটা আমাদের দায়িত্ব', btn: 'প্রেসক্রিপশন আপলোড', link: '#/prescription', color1: '#0e7673', color2: '#21a39e', img: '', active: true, order: 1 },
      { id: uid(), title: 'শীতের স্কিনকেয়ার সেল', sub: 'ময়েশ্চারাইজার ও লিপ কেয়ারে ২৫% পর্যন্ত ছাড়', btn: 'অফার দেখুন', link: '#/shop?cat=skincare', color1: '#f0663a', color2: '#ffa36b', img: '', active: true, order: 2 },
      { id: uid(), title: 'বাসায় বসে ল্যাব টেস্ট', sub: 'স্যাম্পল কালেকশন আপনার দরজায়', btn: 'টেস্ট বুক করুন', link: '#/lab', color1: '#0a5a58', color2: '#3fb8a8', img: '', active: true, order: 3 }
    ];

    const labTests = [
      ['কমপ্লিট ব্লাড কাউন্ট (CBC)', 600, 450, 'রক্তের সাধারণ অবস্থা'], ['ব্লাড সুগার (ফাস্টিং)', 250, 200, 'ডায়াবেটিস পরীক্ষা'],
      ['লিপিড প্রোফাইল', 1500, 1100, 'কোলেস্টেরল পরীক্ষা'], ['থাইরয়েড (TSH)', 1000, 800, 'থাইরয়েড হরমোন'],
      ['HbA1c', 1400, 1050, '৩ মাসের গড় সুগার'], ['ভিটামিন ডি', 3500, 2600, 'ভিটামিন ডি লেভেল']
    ].map(([name, mrp, price, desc]) => ({ id: uid(), name, mrp, price, desc, report: '২৪ ঘণ্টা', active: true }));

    const doctors = [
      ['ডা. রহিমা খাতুন', 'মেডিসিন বিশেষজ্ঞ', 'MBBS, FCPS', 500, 12],
      ['ডা. আসিফ হোসেন', 'শিশু রোগ বিশেষজ্ঞ', 'MBBS, DCH', 600, 9],
      ['ডা. নাসরিন আক্তার', 'চর্ম ও যৌন রোগ', 'MBBS, DDV', 700, 15],
      ['ডা. কামরুল ইসলাম', 'হৃদরোগ বিশেষজ্ঞ', 'MBBS, MD (Cardiology)', 1000, 20]
    ].map(([name, spec, degree, fee, exp]) => ({ id: uid(), name, spec, degree, fee, exp, img: '', days: 'শনি–বৃহস্পতি', time: 'সন্ধ্যা ৬টা–রাত ৯টা', active: true }));

    const posts = [
      { id: uid(), title: 'শীতে ত্বক শুষ্ক হলে কী করবেন', excerpt: 'শীতকালে ত্বকের যত্নের সহজ কিছু উপায়।', body: 'শীতকালে বাতাসে আর্দ্রতা কমে যায়, তাই ত্বক শুষ্ক হয়ে পড়ে।\n\nপ্রতিদিন গোসলের পর ময়েশ্চারাইজার ব্যবহার করুন, পর্যাপ্ত পানি পান করুন এবং খুব গরম পানিতে গোসল এড়িয়ে চলুন।', icon: '❄️', img: '', active: true, createdAt: Date.now() - 2 * DAY },
      { id: uid(), title: 'ডায়াবেটিস নিয়ন্ত্রণে ৫টি অভ্যাস', excerpt: 'দৈনন্দিন জীবনে ছোট পরিবর্তন বড় ফল দেয়।', body: 'নিয়মিত হাঁটা, পরিমিত খাবার, সময়মতো ওষুধ, নিয়মিত সুগার পরীক্ষা এবং পর্যাপ্ত ঘুম — এই পাঁচটি অভ্যাস ডায়াবেটিস নিয়ন্ত্রণে সাহায্য করে।\n\nযেকোনো পরিবর্তনের আগে চিকিৎসকের পরামর্শ নিন।', icon: '🩸', img: '', active: true, createdAt: Date.now() - 5 * DAY },
      { id: uid(), title: 'শিশুর জ্বর হলে করণীয়', excerpt: 'কখন ঘরে যত্ন নেবেন আর কখন ডাক্তার দেখাবেন।', body: 'শিশুর জ্বর হলে পর্যাপ্ত তরল খাওয়ান ও হালকা পোশাক পরান।\n\nজ্বর তিন দিনের বেশি থাকলে, খিঁচুনি হলে বা শিশু নিস্তেজ হয়ে পড়লে দ্রুত চিকিৎসকের কাছে যান।', icon: '👶', img: '', active: true, createdAt: Date.now() - 9 * DAY }
    ];

    const coupons = [
      { id: uid(), code: 'WELCOME10', type: 'percent', value: 10, min: 500, active: true },
      { id: uid(), code: 'FLAT50', type: 'flat', value: 50, min: 800, active: true }
    ];

    const faqs = [
      ['কীভাবে অর্ডার করব?', 'পণ্য খুঁজে কার্টে যোগ করুন অথবা প্রেসক্রিপশন আপলোড করুন, তারপর ঠিকানা দিয়ে অর্ডার কনফার্ম করুন।'],
      ['প্রেসক্রিপশন কি লাগবে?', 'প্রেসক্রিপশন-ভুক্ত (Rx) ওষুধের জন্য বৈধ প্রেসক্রিপশন প্রয়োজন।'],
      ['ডেলিভারিতে কত সময় লাগে?', 'শহর এলাকায় সাধারণত ১২–২৪ ঘণ্টা, অন্যান্য জেলায় ২–৩ দিন।'],
      ['পেমেন্ট কীভাবে করব?', 'ক্যাশ অন ডেলিভারি, বিকাশ, নগদ অথবা কার্ডে পেমেন্ট করা যায়।'],
      ['পণ্য ফেরত দেওয়া যায়?', 'ভুল বা ক্ষতিগ্রস্ত পণ্য ডেলিভারির ৭ দিনের মধ্যে ফেরত দেওয়া যায়।']
    ].map(([q, a], i) => ({ id: uid(), q, a, order: i + 1, active: true }));

    const pages = [
      { id: 'about', title: 'আমাদের সম্পর্কে', body: 'স্বাস্থ্যবাজার একটি অনলাইন ফার্মেসি ও হেলথকেয়ার প্ল্যাটফর্ম। আমরা আসল ওষুধ, হেলথ ও বিউটি পণ্য দ্রুত আপনার দরজায় পৌঁছে দিই।\n\nআমাদের লক্ষ্য — সবার জন্য সহজ, নির্ভরযোগ্য ও সাশ্রয়ী স্বাস্থ্যসেবা।' },
      { id: 'privacy', title: 'প্রাইভেসি পলিসি', body: 'আপনার ব্যক্তিগত তথ্য শুধুমাত্র অর্ডার প্রসেসিং ও ডেলিভারির কাজে ব্যবহার করা হয়। আমরা আপনার তথ্য তৃতীয় পক্ষের কাছে বিক্রি করি না।' },
      { id: 'terms', title: 'শর্তাবলি', body: 'এই সাইট ব্যবহার করে আপনি আমাদের শর্তাবলিতে সম্মত হচ্ছেন। প্রেসক্রিপশন-ভুক্ত ওষুধ বৈধ প্রেসক্রিপশন ছাড়া বিক্রি করা হয় না।' },
      { id: 'return', title: 'রিটার্ন পলিসি', body: 'ভুল, মেয়াদোত্তীর্ণ বা ক্ষতিগ্রস্ত পণ্য ডেলিভারির ৭ দিনের মধ্যে ফেরত দেওয়া যাবে। খোলা ওষুধ ফেরতযোগ্য নয়।' }
    ];

    // ডেমো কাস্টমার ও অর্ডার (ড্যাশবোর্ডের চার্টের জন্য)
    const names = ['রাকিব হাসান', 'সুমাইয়া ইসলাম', 'তানভীর আহমেদ', 'নুসরাত জাহান', 'মাহমুদুল হক', 'ফারহানা ইয়াসমিন'];
    const customers = names.map((name, i) => ({ id: uid(), name, phone: '0171' + (1000000 + i * 13579), pass: '1234', address: 'বাড়ি ' + (i + 3) + ', রোড ' + (i + 1) + ', ধানমন্ডি, ঢাকা', createdAt: Date.now() - (20 - i) * DAY }));
    const statuses = ['delivered', 'delivered', 'delivered', 'shipped', 'processing', 'confirmed', 'pending', 'cancelled'];
    const orders = [];
    let seq = 1000;
    for (let i = 0; i < 26; i++) {
      const c = customers[i % customers.length];
      const items = [];
      const n = 1 + (i % 3);
      for (let j = 0; j < n; j++) {
        const p = products[(i * 7 + j * 5) % products.length];
        items.push({ id: p.id, name: p.name, icon: p.icon, price: p.price, qty: 1 + ((i + j) % 2) });
      }
      const subtotal = items.reduce((s, x) => s + x.price * x.qty, 0);
      const delivery = subtotal >= 999 ? 0 : 60;
      const daysAgo = Math.floor(i / 2);
      const status = daysAgo < 1 ? (i % 2 ? 'pending' : 'confirmed') : statuses[i % statuses.length];
      orders.push({
        id: 'SB' + (++seq), customerId: c.id, name: c.name, phone: c.phone, address: c.address, area: 'ঢাকা',
        items, subtotal, discount: 0, delivery, total: subtotal + delivery, coupon: '',
        payment: ['cod', 'bkash', 'nagad', 'card'][i % 4], paid: status === 'delivered',
        status, note: '', createdAt: Date.now() - daysAgo * DAY - i * 3600000,
        history: [{ status: 'pending', at: Date.now() - daysAgo * DAY - i * 3600000 }]
      });
    }
    orders.reverse();

    return {
      version: 1,
      settings: {
        siteName: 'স্বাস্থ্যবাজার', tagline: 'আপনার বিশ্বস্ত অনলাইন ফার্মেসি ও হেলথকেয়ার',
        logoIcon: '✚', logoImg: '',
        phone: '01XXX-XXXXXX', whatsapp: '01XXX-XXXXXX', email: 'support@example.com',
        address: 'হাউজ ১০, রোড ৫, ধানমন্ডি, ঢাকা',
        announcement: '🎉 শীতকালীন অফার চলছে — নির্বাচিত পণ্যে ৩০% পর্যন্ত ছাড়!', announcementOn: true,
        flashText: '⚡ ফ্ল্যাশ সেল', deliveryCharge: 60, outsideDhakaCharge: 120, freeDeliveryMin: 999,
        expressText: '১২–২৪ ঘণ্টায় ডেলিভারি',
        primary: '#0e7673', primaryDark: '#0a5a58', accent: '#f0663a',
        statCustomers: '১০ লাখ+', statProducts: '২০ হাজার+', statDistricts: '৬৪', statDelivery: '১২ ঘণ্টা',
        facebook: '#', instagram: '#', youtube: '#', linkedin: '#',
        payments: 'ক্যাশ অন ডেলিভারি,বিকাশ,নগদ,রকেট,Visa,Mastercard',
        adminPass: 'admin123', orderSeq: seq
      },
      categories, products, sections, banners, labTests, doctors, posts, coupons, faqs, pages, customers, orders,
      prescriptions: [], labBookings: [], appointments: [], messages: []
    };
  }

  const DB = {
    data: null,
    load() {
      try { this.data = JSON.parse(localStorage.getItem(KEY)); } catch (e) { this.data = null; }
      if (!this.data || !this.data.settings) { this.data = seed(); this.save(); }
      return this.data;
    },
    save() {
      try { localStorage.setItem(KEY, JSON.stringify(this.data)); return true; }
      catch (e) { alert('ব্রাউজারের স্টোরেজ পূর্ণ হয়ে গেছে। কিছু ছবি মুছে ফেলুন বা ছোট ছবি ব্যবহার করুন।'); return false; }
    },
    reset() { localStorage.removeItem(KEY); this.data = null; return this.load(); },
    all(c) { return this.data[c] || (this.data[c] = []); },
    get(c, id) { return this.all(c).find(x => String(x.id) === String(id)); },
    insert(c, o) { o.id = o.id || uid(); o.createdAt = o.createdAt || Date.now(); this.all(c).unshift(o); this.save(); return o; },
    update(c, id, patch) { const o = this.get(c, id); if (o) { Object.assign(o, patch); this.save(); } return o; },
    remove(c, id) { this.data[c] = this.all(c).filter(x => String(x.id) !== String(id)); this.save(); },
    nextOrderId() { this.data.settings.orderSeq = (this.data.settings.orderSeq || 1000) + 1; return 'SB' + this.data.settings.orderSeq; },
    uid
  };

  const bn = s => String(s ?? '').replace(/\d/g, d => '০১২৩৪৫৬৭৮৯'[d]);
  const tk = n => '৳' + bn(Math.round(+n || 0).toLocaleString('en-IN'));
  const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const fmtDate = t => new Date(t).toLocaleString('bn-BD', { day: 'numeric', month: 'short', year: 'numeric', hour: 'numeric', minute: '2-digit' });

  // ছবি ছোট করে base64 বানানো (স্টোরেজ বাঁচাতে)
  function compressImage(file, max = 800, q = 0.72) {
    return new Promise((res, rej) => {
      const r = new FileReader();
      r.onerror = rej;
      r.onload = () => {
        const im = new Image();
        im.onerror = rej;
        im.onload = () => {
          const sc = Math.min(1, max / Math.max(im.width, im.height));
          const c = document.createElement('canvas');
          c.width = Math.round(im.width * sc); c.height = Math.round(im.height * sc);
          const ctx = c.getContext('2d'); ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, c.width, c.height);
          ctx.drawImage(im, 0, 0, c.width, c.height);
          res(c.toDataURL('image/jpeg', q));
        };
        im.src = r.result;
      };
      r.readAsDataURL(file);
    });
  }

  const STATUS = {
    pending: { l: 'নতুন', c: '#f59e0b' }, confirmed: { l: 'কনফার্মড', c: '#3b82f6' },
    processing: { l: 'প্যাকিং হচ্ছে', c: '#8b5cf6' }, shipped: { l: 'পথে আছে', c: '#0ea5e9' },
    delivered: { l: 'ডেলিভারড', c: '#16a34a' }, cancelled: { l: 'বাতিল', c: '#dc2626' }
  };
  const PAY = { cod: 'ক্যাশ অন ডেলিভারি', bkash: 'বিকাশ', nagad: 'নগদ', card: 'কার্ড' };

  function applyTheme() {
    const s = DB.data.settings, r = document.documentElement.style;
    r.setProperty('--brand', s.primary || '#0e7673');
    r.setProperty('--brand-dark', s.primaryDark || '#0a5a58');
    r.setProperty('--accent', s.accent || '#f0663a');
  }

  Object.assign(window, { DB, bn, tk, esc, fmtDate, compressImage, STATUS, PAY, applyTheme });
  DB.load();

  // অন্য ট্যাবে (যেমন অ্যাডমিনে) পরিবর্তন হলে এখানে লাইভ আপডেট
  window.addEventListener('storage', e => {
    if (e.key === KEY) { DB.data = null; DB.load(); applyTheme(); if (window.onDBChange) window.onDBChange(); }
  });
})();
