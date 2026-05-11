<?php
// components/ai_bubble.php
?>
<style>
    .ai-bubble-container {
        position: fixed;
        bottom: 2rem;
        right: 2rem;
        z-index: 9999;
    }

    .ai-bubble-main {
        position: relative;
        width: 64px;
        height: 64px;
        background: linear-gradient(135deg, #d946ef 0%, #7c3aed 100%);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        box-shadow: 0 10px 25px -5px rgba(192, 38, 211, 0.5), 
                    inset 0 0 12px rgba(255, 255, 255, 0.2);
        transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        border: 1px solid rgba(255, 255, 255, 0.2);
    }

    .ai-bubble-main:hover {
        transform: translateY(-5px) scale(1.05);
        box-shadow: 0 15px 30px -5px rgba(192, 38, 211, 0.6);
    }

    .ai-bubble-main i {
        font-size: 24px;
        color: white;
        text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    /* Outer glow animation */
    .ai-glow {
        position: absolute;
        inset: -4px;
        background: linear-gradient(135deg, #d946ef, #7c3aed);
        border-radius: 50%;
        z-index: -1;
        opacity: 0.3;
        filter: blur(8px);
        animation: ai-glow-pulse 3s infinite;
    }

    @keyframes ai-glow-pulse {
        0%, 100% { transform: scale(1); opacity: 0.3; }
        50% { transform: scale(1.1); opacity: 0.5; }
    }

    /* Floating tooltips */
    .ai-tooltip {
        position: absolute;
        right: 80px;
        top: 50%;
        transform: translateY(-50%) translateX(10px);
        background: #101622;
        border: 1px solid rgba(217, 70, 239, 0.3);
        padding: 10px 18px;
        border-radius: 14px;
        color: white;
        font-size: 13px;
        font-weight: 600;
        white-space: nowrap;
        opacity: 0;
        visibility: hidden;
        transition: all 0.3s ease;
        box-shadow: 0 10px 20px rgba(0,0,0,0.4);
    }

    .ai-bubble-container:hover .ai-tooltip {
        opacity: 1;
        visibility: visible;
        transform: translateY(-50%) translateX(0);
    }

    /* Robot bounce animation */
    .ai-robot-anim {
        animation: ai-robot-float 2s ease-in-out infinite;
    }

    @keyframes ai-robot-float {
        0%, 100% { transform: translateY(0); }
        50% { transform: translateY(-4px); }
    }

    /* Particle rings */
    .ai-ring {
        position: absolute;
        border: 2px solid rgba(217, 70, 239, 0.5);
        border-radius: 50%;
        animation: ai-ring-ping 2s cubic-bezier(0, 0, 0.2, 1) infinite;
    }

    @keyframes ai-ring-ping {
        75%, 100% { transform: scale(2); opacity: 0; }
    }
</style>

<div class="ai-bubble-container">
    <a href="ai_assistente.php" class="block">
        <div class="ai-tooltip">
            Olá! Sou o <span style="color: #d946ef">OnMeck</span>, como posso ajudar?
        </div>
        <div class="ai-bubble-main">
            <div class="ai-glow"></div>
            <div class="ai-ring" style="width: 100%; height: 100%"></div>
            <img src="ia.png" alt="OnMeck AI" class="w-12 h-12 object-contain ai-robot-anim">
        </div>
    </a>
</div>
