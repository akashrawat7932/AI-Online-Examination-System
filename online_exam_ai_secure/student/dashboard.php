<?php include_once 'hearder.php' ?>
   
        <!-- Main Body Content -->
        <div class="container">
            <div class="card">
                <h2 class="card-title">Active & Scheduled Exams</h2>
                
                <?php if (!empty($ex)): ?>
                    <div class="exam-grid">
                        <?php foreach ($ex as $x): ?>
                            <div class="exam-card">
                                <div>
                                    <div class="exam-title"><?= e($x['exam_title']) ?></div>
                                    <div class="subject-name">Subject: <?= e($x['subject_name']) ?></div>
                                </div>
                                <a href="start_exam.php?id=<?= urlencode($x['id']) ?>" class="btn btn-primary">Start Exam</a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="empty-state">No active or scheduled exams available at this moment.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

</body>
</html>