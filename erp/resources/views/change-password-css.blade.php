<style>        
    .password-modal.modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
        z-index: 1000;
        justify-content: center;
        align-items: center;
        animation: fadeIn 0.3s ease;
    }
    
    .password-modal .modal-content {
        background-color: white;
        width: 90%;
        max-width: 500px;
        border-radius: 12px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        padding: 30px;
        position: relative;
        animation: slideUp 0.4s ease;
    }
    
    .password-modal .close-btn {
        position: absolute;
        top: 15px;
        right: 20px;
        font-size: 24px;
        cursor: pointer;
        color: #999;
        transition: color 0.3s;
    }
    
    .password-modal .close-btn:hover {
        color: #333;
    }
    
    .password-modal h2 {
        color: #333;
        margin-bottom: 20px;
        text-align: center;
        font-size: 30px;
        font-weight: bold;
    }
    
    .password-modal .form-group {
        margin-bottom: 20px;
        text-align: left;
    }
    
    .password-modal label {
        display: block;
        margin-bottom: 8px;
        color: #555;
        font-weight: 500;
    }
    
    .password-modal input {
        width: 100%;
        padding: 12px 15px;
        border: 1px solid #ddd;
        border-radius: 6px;
        font-size: 16px;
        transition: border 0.3s;
    }
    
    .password-modal input:focus {
        border-color: #4a6cf7;
        outline: none;
        box-shadow: 0 0 0 2px rgba(74, 108, 247, 0.2);
    }
    
    .password-modal .password-strength {
        height: 5px;
        margin-top: 8px;
        border-radius: 3px;
        background-color: #eee;
        overflow: hidden;
    }
    
    .password-modal .password-strength-bar {
        height: 100%;
        width: 0;
        transition: width 0.3s, background-color 0.3s;
    }
    
    .password-modal .error-message {
        color: #e74c3c;
        font-size: 14px;
        margin-top: 5px;
        display: none;
    }
    
    .password-modal .success-message {
        background-color: #2ecc71;
        color: white;
        padding: 12px;
        border-radius: 6px;
        margin-bottom: 20px;
        display: none;
    }
    
    .password-modal .submit-btn {
        width: 100%;
        padding: 14px;
        background: #4a6cf7;
        color: white;
        border: none;
        border-radius: 6px;
        font-size: 16px;
        cursor: pointer;
        transition: background 0.3s;
    }
    
    .password-modal .submit-btn:hover {
        background: #3a5ce5;
    }
    
    .password-modal .submit-btn:disabled {
        background: #ccc;
        cursor: not-allowed;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    
    @keyframes slideUp {
        from { 
            opacity: 0;
            transform: translateY(30px);
        }
        to { 
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    @media (max-width: 600px) {
        .password-modal .modal-content {
            padding: 20px;
        }
    }
</style>