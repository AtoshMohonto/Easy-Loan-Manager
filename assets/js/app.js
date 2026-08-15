document.addEventListener('DOMContentLoaded', function () {
    // Mobile sidebar toggle
    var toggle = document.getElementById('sidebarToggle');
    var sidebar = document.getElementById('appSidebar');
    if (toggle && sidebar) {
        toggle.addEventListener('click', function () {
            sidebar.classList.toggle('open');
        });
    }

    // Generic destructive-action confirm
    document.querySelectorAll('[data-confirm]').forEach(function (el) {
        el.addEventListener('click', function (e) {
            if (!window.confirm(el.getAttribute('data-confirm'))) {
                e.preventDefault();
            }
        });
    });

    // Amount-in-words preview (mirrors includes/functions.php logic)
    var BN_ONES = {0:'শূন্য',1:'এক',2:'দুই',3:'তিন',4:'চার',5:'পাঁচ',6:'ছয়',7:'সাত',8:'আট',9:'নয়',10:'দশ',11:'এগারো',12:'বারো',13:'তেরো',14:'চৌদ্দ',15:'পনেরো',16:'ষোলো',17:'সতেরো',18:'আঠারো',19:'উনিশ'};
    var BN_TENS = {2:'বিশ',3:'ত্রিশ',4:'চল্লিশ',5:'পঞ্চাশ',6:'ষাট',7:'সত্তর',8:'আশি',9:'নব্বই'};
    var BN_TEEN = {21:'একুশ',22:'বাইশ',23:'তেইশ',24:'চব্বিশ',25:'পঁচিশ',26:'ছাব্বিশ',27:'সাতাশ',28:'আটাশ',29:'ঊনত্রিশ',31:'একত্রিশ',32:'বত্রিশ',33:'তেত্রিশ',34:'চৌত্রিশ',35:'পঁয়ত্রিশ',36:'ছত্রিশ',37:'সাঁইত্রিশ',38:'আটত্রিশ',39:'ঊনচল্লিশ',41:'একচল্লিশ',42:'বিয়াল্লিশ',43:'তেতাল্লিশ',44:'চুয়াল্লিশ',45:'পঁয়তাল্লিশ',46:'ছেচল্লিশ',47:'সাতচল্লিশ',48:'আটচল্লিশ',49:'ঊনপঞ্চাশ',51:'একান্ন',52:'বায়ান্ন',53:'তিপ্পান্ন',54:'চুয়ান্ন',55:'পঞ্চান্ন',56:'ছাপ্পান্ন',57:'সাতান্ন',58:'আটান্ন',59:'ঊনষাট',61:'একষট্টি',62:'বাষট্টি',63:'তেষট্টি',64:'চৌষট্টি',65:'পঁয়ষট্টি',66:'ছেষট্টি',67:'সাতষট্টি',68:'আটষট্টি',69:'ঊনসত্তর',71:'একাত্তর',72:'বাহাত্তর',73:'তিয়াত্তর',74:'চুয়াত্তর',75:'পঁচাত্তর',76:'ছিয়াত্তর',77:'সাতাত্তর',78:'আটাত্তর',79:'ঊনআশি',81:'একাশি',82:'বিরাশি',83:'তিরাশি',84:'চুরাশি',85:'পঁচাশি',86:'ছিয়াশি',87:'সাতাশি',88:'অষ্টাশি',89:'ঊননব্বই',91:'একানব্বই',92:'বিরানব্বই',93:'তিরানব্বই',94:'চুরানব্বই',95:'পঁচানব্বই',96:'ছিয়ানব্বই',97:'সাতানব্বই',98:'আটানব্বই',99:'নিরানব্বই'};
    function bnWordNum(n) {
        if (n < 20) return BN_ONES[n];
        if (BN_TEEN[n]) return BN_TEEN[n];
        return BN_TENS[Math.floor(n / 10)] + BN_ONES[n % 10];
    }
    function bnIntWords(num) {
        if (num <= 0) return '';
        var crore = Math.floor(num / 10000000); num %= 10000000;
        var lakh = Math.floor(num / 100000); num %= 100000;
        var thousand = Math.floor(num / 1000); num %= 1000;
        var hundred = Math.floor(num / 100); num %= 100;
        var rest = num;
        var parts = [];
        if (crore > 0) parts.push(bnWordNum(crore) + ' কোটি');
        if (lakh > 0) parts.push(bnWordNum(lakh) + ' লক্ষ');
        if (thousand > 0) parts.push(bnWordNum(thousand) + ' হাজার');
        if (hundred > 0) parts.push(bnWordNum(hundred) + ' শত');
        if (rest > 0) parts.push(bnWordNum(rest));
        return parts.join(' ');
    }
    function bnAmountWords(amount) {
        var whole = Math.floor(amount);
        var dec = Math.round((amount - whole) * 100);
        var words = whole === 0 ? 'শূন্য' : bnIntWords(whole);
        var result = words + ' টাকা';
        if (dec > 0) result += ' ' + bnIntWords(dec) + ' পয়সা';
        return result + ' মাত্র';
    }
    var EN_ONES = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
    var EN_TENS = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
    function enTwo(n) { if (n < 20) return EN_ONES[n]; return (EN_TENS[Math.floor(n / 10)] + ' ' + EN_ONES[n % 10]).trim(); }
    function enThree(n) {
        var s = '';
        if (n >= 100) { s += EN_ONES[Math.floor(n / 100)] + ' Hundred '; n %= 100; }
        if (n > 0) s += enTwo(n);
        return s.trim();
    }
    function enIntWords(num) {
        if (num <= 0) return '';
        var crore = Math.floor(num / 10000000); num %= 10000000;
        var lakh = Math.floor(num / 100000); num %= 100000;
        var thousand = Math.floor(num / 1000); num %= 1000;
        var hundred = num;
        var parts = [];
        if (crore > 0) parts.push(enThree(crore) + ' Crore');
        if (lakh > 0) parts.push(enTwo(lakh) + ' Lakh');
        if (thousand > 0) parts.push(enTwo(thousand) + ' Thousand');
        if (hundred > 0) parts.push(enThree(hundred));
        return parts.join(' ');
    }
    function enAmountWords(amount) {
        var whole = Math.floor(amount);
        var dec = Math.round((amount - whole) * 100);
        var words = whole === 0 ? 'Zero' : enIntWords(whole);
        var result = words + ' Taka';
        if (dec > 0) result += ' and ' + enIntWords(dec) + ' Poisha';
        return result + ' Only';
    }
    function amountInWords(amount) {
        return (document.documentElement.lang === 'bn') ? bnAmountWords(amount) : enAmountWords(amount);
    }

    document.querySelectorAll('[data-words-target]').forEach(function (input) {
        var preview = document.querySelector(input.getAttribute('data-words-target'));
        if (!preview) return;
        function update() {
            var value = parseFloat(input.value);
            if (!isFinite(value) || value <= 0) {
                preview.textContent = '';
                preview.classList.add('d-none');
                return;
            }
            preview.textContent = amountInWords(value);
            preview.classList.remove('d-none');
        }
        input.addEventListener('input', update);
        update();
    });

    // Print trigger
    document.querySelectorAll('.btn-print').forEach(function (btn) {
        btn.addEventListener('click', function () {
            window.print();
        });
    });
});
