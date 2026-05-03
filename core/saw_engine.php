<?php
/**
 * SAW Engine - Simple Additive Weighting
 * Metode: Normalisasi matriks keputusan → kalikan bobot AHP → jumlahkan skor
 */

require_once __DIR__ . '/ahp_loader.php';

class SAWEngine {
    
    private $ahp;
    private $patients = [];
    private $results  = [];
    private $normalizedMatrix = [];
    private $criteria;
    private $criteriaTypes;
    
    public function __construct(array $patients) {
        $this->ahp          = new AHPLoader();
        $this->patients     = $patients;
        $this->criteria     = $this->ahp->getCriteria();
        $this->criteriaTypes = $this->ahp->getCriteriaTypes();
    }
    
    /**
     * Jalankan perhitungan SAW lengkap
     */
    public function calculate(): array {
        if (empty($this->patients)) return [];
        
        $weights = $this->ahp->getWeights();
        
        // Step 1: Cari nilai max/min tiap kriteria
        $maxVal = [];
        $minVal = [];
        foreach ($this->criteria as $c) {
            $vals = array_column($this->patients, $c);
            $vals = array_filter($vals, fn($v) => $v !== null && $v !== '');
            $maxVal[$c] = !empty($vals) ? max($vals) : 1;
            $minVal[$c] = !empty($vals) ? min($vals) : 0;
        }
        
        // Step 2: Normalisasi tiap nilai
        $this->normalizedMatrix = [];
        foreach ($this->patients as $idx => $patient) {
            $row = ['patient_id' => $patient['patient_id']];
            foreach ($this->criteria as $c) {
                $val = isset($patient[$c]) ? (float)$patient[$c] : 0;
                $type = $this->criteriaTypes[$c];
                
                if ($type === 'benefit') {
                    // Benefit: Rij = Xij / max(Xj)
                    $norm = ($maxVal[$c] != 0) ? $val / $maxVal[$c] : 0;
                } else {
                    // Cost: Rij = min(Xj) / Xij
                    $norm = ($val != 0) ? $minVal[$c] / $val : 0;
                }
                $row[$c] = round($norm, 6);
            }
            $this->normalizedMatrix[$idx] = $row;
        }
        
        // Step 3: Hitung skor SAW = Σ (Wj * Rij)
        $this->results = [];
        foreach ($this->patients as $idx => $patient) {
            $score = 0;
            $detail = [];
            foreach ($this->criteria as $c) {
                $normVal = $this->normalizedMatrix[$idx][$c];
                $weighted = $weights[$c] * $normVal;
                $score += $weighted;
                $detail[$c] = [
                    'original'   => isset($patient[$c]) ? (float)$patient[$c] : 0,
                    'normalized' => $normVal,
                    'weight'     => $weights[$c],
                    'weighted'   => round($weighted, 6),
                ];
            }
            
            $this->results[] = [
                'patient_id'  => $patient['patient_id'],
                'age'         => $patient['age'] ?? '-',
                'bmi'         => $patient['bmi'] ?? '-',
                'd1_spo2_min' => $patient['d1_spo2_min'] ?? '-',
                'd1_heartrate_min' => $patient['d1_heartrate_min'] ?? '-',
                'saw_score'   => round($score, 6),
                'detail'      => $detail,
            ];
        }
        
        // Step 4: Urutkan dari skor tertinggi (prioritas tertinggi)
        usort($this->results, fn($a, $b) => $b['saw_score'] <=> $a['saw_score']);
        
        // Step 5: Tambahkan ranking & label prioritas
        foreach ($this->results as $i => &$r) {
            $r['rank']     = $i + 1;
            $r['priority'] = $this->getPriorityLabel($r['saw_score'], $i, count($this->results));
        }
        
        return $this->results;
    }
    
    /**
     * Label prioritas berdasarkan posisi dan skor
     */
    private function getPriorityLabel(float $score, int $index, int $total): string {
        $pct = ($total > 1) ? $index / ($total - 1) : 0;
        if ($pct <= 0.25) return 'KRITIS';
        if ($pct <= 0.50) return 'TINGGI';
        if ($pct <= 0.75) return 'SEDANG';
        return 'RENDAH';
    }
    
    public function getResults(): array {
        return $this->results;
    }
    
    public function getNormalizedMatrix(): array {
        return $this->normalizedMatrix;
    }
    
    public function getAHP(): AHPLoader {
        return $this->ahp;
    }
    
    /**
     * Ringkasan statistik hasil SAW
     */
    public function getSummary(): array {
        if (empty($this->results)) return [];
        $scores = array_column($this->results, 'saw_score');
        $priorities = array_count_values(array_column($this->results, 'priority'));
        return [
            'total'        => count($this->results),
            'max_score'    => max($scores),
            'min_score'    => min($scores),
            'avg_score'    => round(array_sum($scores) / count($scores), 6),
            'by_priority'  => [
                'KRITIS' => $priorities['KRITIS'] ?? 0,
                'TINGGI' => $priorities['TINGGI'] ?? 0,
                'SEDANG' => $priorities['SEDANG'] ?? 0,
                'RENDAH' => $priorities['RENDAH'] ?? 0,
            ],
        ];
    }
}
?>
