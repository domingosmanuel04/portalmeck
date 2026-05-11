<?php
// components/footer_datetime.php
?>
<div class="fixed bottom-6 right-6 z-[100] animate__animated animate__fadeInUp">
    <div class="bg-slate-900/80 backdrop-blur-xl border border-slate-700/50 p-4 rounded-2xl shadow-2xl flex items-center gap-6 group hover:border-blue-500/50 transition-all duration-500">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-blue-600/10 rounded-xl flex items-center justify-center text-blue-400 border border-blue-500/20 group-hover:bg-blue-600/20 transition-all">
                <i class="fa-solid fa-clock text-lg"></i>
            </div>
            <div class="flex flex-col">
                <span class="text-[9px] font-black uppercase tracking-widest text-slate-500 leading-none mb-1">Hora Atual</span>
                <span x-data="{ time: new Date().toLocaleTimeString('pt-PT', {hour: '2-digit', minute: '2-digit'}) }" 
                      x-init="setInterval(() => time = new Date().toLocaleTimeString('pt-PT', {hour: '2-digit', minute: '2-digit'}), 1000)" 
                      x-text="time" 
                      class="text-base font-bold text-white tabular-nums tracking-tight"></span>
            </div>
        </div>
        <div class="w-px h-8 bg-slate-800"></div>
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-emerald-600/10 rounded-xl flex items-center justify-center text-emerald-400 border border-emerald-500/20 group-hover:bg-emerald-600/20 transition-all">
                <i class="fa-solid fa-calendar-day text-lg"></i>
            </div>
            <div class="flex flex-col">
                <span class="text-[9px] font-black uppercase tracking-widest text-slate-500 leading-none mb-1">Data de Hoje</span>
                <span class="text-xs font-bold text-slate-300">
                    <?php $meses = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez']; echo date('d').' '.$meses[date('n')-1].', '.date('Y'); ?>
                </span>
            </div>
        </div>
    </div>
</div>