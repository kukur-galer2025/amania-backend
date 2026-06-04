<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Sertifikat Kelulusan</title>
    <style>
        @page { 
            margin: 0px; 
            size: a4 landscape;
        }
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f0f4f8;
            text-align: center;
        }
        
        /* Container to fill 1 page exactly */
        .cert-outer {
            width: 1020px;
            height: 700px;
            margin: 40px auto 0 auto;
            border: 3px solid #b8860b; /* Goldenrod */
            background-color: white;
            position: relative;
            box-sizing: border-box;
            padding: 10px;
        }
        
        .cert-inner {
            border: 1px solid #d4af37; /* Metallic Gold */
            width: 100%;
            height: 100%;
            position: relative;
            box-sizing: border-box;
        }
        
        .cert-content {
            padding-top: 30px;
        }
        
        /* Watermark */
        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 60%;
            opacity: 0.05;
            z-index: -1;
        }

        .logo {
            max-width: 220px;
            margin-bottom: 20px;
        }
        .pre-title {
            font-size: 14px;
            color: #b8860b;
            letter-spacing: 4px;
            text-transform: uppercase;
            margin-bottom: 10px;
            font-weight: bold;
        }
        .title {
            font-size: 46px;
            font-weight: bold;
            color: #1a202c;
            margin-bottom: 20px;
            font-family: 'Georgia', serif;
            letter-spacing: 2px;
        }
        .subtitle {
            font-size: 18px;
            color: #4a5568;
            margin-bottom: 15px;
            font-style: italic;
            font-family: 'Georgia', serif;
        }
        .name {
            font-size: 46px;
            font-weight: bold;
            color: #b8860b;
            margin-bottom: 20px;
            font-family: 'Georgia', serif;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        .course-title {
            font-size: 28px;
            font-weight: bold;
            color: #2d3748;
            margin: 15px 0;
            line-height: 1.3;
        }
        .meta-info {
            font-size: 16px;
            color: #718096;
            margin-top: 10px;
            margin-bottom: 30px;
        }
        .meta-info span {
            font-weight: bold;
            color: #1a202c;
        }
        .date {
            font-size: 16px;
            color: #4a5568;
            font-weight: bold;
        }
        .cert-code {
            position: absolute;
            bottom: 25px;
            left: 0;
            width: 100%;
            text-align: center;
            font-size: 12px;
            color: #a0aec0;
            letter-spacing: 1px;
        }
        .seal {
            position: absolute;
            bottom: 25px;
            right: 40px;
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background-color: #b8860b;
            color: white;
            text-align: center;
            border: 3px double white;
            outline: 2px solid #b8860b;
        }
        .seal-text {
            font-family: 'Georgia', serif;
            font-size: 16px;
            font-weight: bold;
            margin-top: 38px;
            display: block;
            letter-spacing: 1px;
        }
    </style>
</head>
<body>
    <div class="cert-outer">
        <div class="cert-inner">
            <div class="cert-content">
                <?php 
                    $path = 'd:/laragon/www/amania-frontend/public/logo-amania.png';
                    $type = pathinfo($path, PATHINFO_EXTENSION);
                    if (file_exists($path)) {
                        $data = file_get_contents($path);
                        $base64 = 'data:image/' . $type . ';base64,' . base64_encode($data);
                        echo '<img src="' . $base64 . '" class="watermark" alt="Watermark">';
                        echo '<img src="' . $base64 . '" class="logo" alt="Logo Amania">';
                    }
                ?>
                
                <div class="pre-title">Sertifikat Resmi</div>
            <div class="title">CERTIFICATE OF COMPLETION</div>
            
            <div class="subtitle">Diberikan dengan penuh kebanggaan kepada:</div>
            
            <div class="name">{{ $user->name }}</div>
            
            <div class="subtitle">Atas dedikasi dan keberhasilannya dalam menyelesaikan program kelas:</div>
            
            <div class="course-title">{{ $course->title }}</div>
            
            <div class="meta-info">
                Instruktur: <span>{{ $course->instructor ? $course->instructor->name : 'Instruktur Resmi' }}</span> 
                &nbsp; | &nbsp; 
                Total Waktu Belajar: <span>{{ $totalMinutes }} Menit</span> 
                &nbsp; | &nbsp; 
                Status: <span>LULUS</span>
            </div>
            
            <div class="date">
                Diterbitkan pada tanggal {{ \Carbon\Carbon::parse($certificate->issued_at)->translatedFormat('d F Y') }}
            </div>
            
            <div class="seal">
                <span class="seal-text">CERTIFIED</span>
            </div>

            <div class="cert-code">ID Sertifikat: {{ $certificate->certificate_code }} | Validasi di Amania Platform</div>
            </div>
        </div>
    </div>
</body>
</html>
