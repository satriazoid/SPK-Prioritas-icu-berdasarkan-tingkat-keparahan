<?php
/**
 * WP Engine - Weighted Product
 * Metode: S(i) = Π (Xij ^ Wj)  untuk benefit
 *                Π (Xij ^ -Wj) untuk cost
 * Lalu V(i) = S(i) / Σ S(k)
 */

require_once __DIR__ . '/ahp_loader.php';

class WPEngine {
    
    private $ahp;
    private $patients = [];
    private $results  = [];
    private $sValues  = [];
    private $criteria;
    private $criteriaTypes;
    
    public function __construct(array $patients) {
        $this->ahp          = new AHPLoader();
        $this->patients     = $patients;
        $this->criteria     = $this->ahp->getCriteria();
        $this->criteriaTypes = $this->ahp->getCriteriaTypes();
    }
    
    /**
     * Jalankan perhitungan WP lengkap
     */
    public function calculate(): array {
        if (empty($this->patients)) return [];
        
        $weights = $this->ahp->getWeights();
        
        // Step 1: Hitung S(i) untuk setiap pasien
        // S(i) = Π Xij^Wj  (benefit) atau Π Xij^(-Wj) (cost)
        $this->sValues = [];
        foreach ($this->patients as $idx => $patient) {
            $s      = 1.0;
            $detail = [];
            foreach ($this->criteria as $c) {
                $val  = isset($patient[$c]) ? (float)$patient[$c] : 0.0001;
                if ($val <= 0) $val = 0.0001; // hindari log(0)
                
                $w    = $weights[$c];
                $type = $this->criteriaTypes[$c];
                
                // Cost → pangkat negatif (semakin kecil nilainya, semakin tinggi skornya)
                $exp  = ($type === 'benefit') ? $w : -$w;
                $term = pow($val, $exp);
                $s   *= $term;
                
                $detail[$c] = [
                    'original' => $val,
                    'weight'   => $w,
                    'type'     => $type,
                    'exponent' => round($exp, 6),
                    'term'     => round($term, 8),
                ];
            }
            $this->sValues[$idx] = [
                'patient_id' => $patient['patient_id'],
                's_value'    => $s,
                'detail'     => $detail,
                'raw'        => $patient,
            ];
        }
        
        // Step 2: Hitung total S untuk normalisasi
        $totalS = array_sum(array_column($this->sValues, 's_value'));
        
        // Step 3: Hitung V(i) = S(i) / Σ S(k)
        $this->results = [];
        foreach ($this->sValues as $idx => $sv) {
            $vScore = ($totalS > 0) ? $sv['s_value'] / $totalS : 0;
            $patient = $sv['raw'];
            $this->results[] = [
                'patient_id'       => $patient['patient_id'],
                'age'              => $patient['age'] ?? '-',
                'bmi'              => $patient['bmi'] ?? '-',
                'd1_spo2_min'      => $patient['d1_spo2_min'] ?? '-',
                'd1_heartrate_min' => $patient['d1_heartrate_min'] ?? '-',
                's_value'          => round($sv['s_value'], 8),
                'wp_score'         => round($vScore, 8),
                'detail'           => $sv['detail'],
                'total_s'          => round($totalS, 8),
            ];
        }
        
        // Step 4: Urutkan dari WP score tertinggi
        usort($this->results, fn($a, $b) => $b['wp_score'] <=> $a['wp_score']);
        
        // Step 5: Ranking & label prioritas
        foreach ($this->results as $i => &$r) {
            $r['rank']     = $i + 1;
            $r['priority'] = $this->getPriorityLabel($i, count($this->results));
        }
        
        return $this->results;
    }
    
    /**
     * Label prioritas berdasarkan posisi ranking
     */
    private function getPriorityLabel(int $index, int $total): string {
        $pct = ($total > 1) ? $index / ($total - 1) : 0;
        if ($pct <= 0.25) return 'KRITIS';
        if ($pct <= 0.50) return 'TINGGI';
        if ($pct <= 0.75) return 'SEDANG';
        return 'RENDAH';
    }
    
    public function getResults(): array {
        return $this->results;
    }
    
    public function getSValues(): array {
        return $this->sValues;
    }
    
    public function getAHP(): AHPLoader {
        return $this->ahp;
    }
    
    /**
     * Ringkasan statistik hasil WP
     */
    public function getSummary(): array {
        if (empty($this->results)) return [];
        $scores = array_column($this->results, 'wp_score');
        $priorities = array_count_values(array_column($this->results, 'priority'));
        return [
            'total'       => count($this->results),
            'max_score'   => max($scores),
            'min_score'   => min($scores),
            'avg_score'   => round(array_sum($scores) / count($scores), 8),
            'by_priority' => [
                'KRITIS' => $priorities['KRITIS'] ?? 0,
                'TINGGI' => $priorities['TINGGI'] ?? 0,
                'SEDANG' => $priorities['SEDANG'] ?? 0,
                'RENDAH' => $priorities['RENDAH'] ?? 0,
            ],
        ];
    }
    
    /**
     * Bandingkan ranking WP vs SAW
     * Input: hasil SAW yang sudah diurutkan
     */
    public function compareWithSAW(array $sawResults): array {
        $wpMap  = [];
        foreach ($this->results as $r) {
            $wpMap[$r['patient_id']] = $r['rank'];
        }
        $sawMap = [];
        foreach ($sawResults as $r) {
            $sawMap[$r['patient_id']] = $r['rank'];
        }
        
        $comparison = [];
        foreach ($this->results as $r) {
            $pid  = $r['patient_id'];
            $sawRank = $sawMap[$pid] ?? '-';
            $wpRank  = $wpMap[$pid];
            $diff    = ($sawRank !== '-') ? abs($wpRank - $sawRank) : '-';
            $comparison[] = [
                'patient_id' => $pid,
                'wp_rank'    => $wpRank,
                'saw_rank'   => $sawRank,
                'rank_diff'  => $diff,
                'same_rank'  => ($diff === 0),
            ];
        }
        // Urutkan berdasarkan WP rank
        usort($comparison, fn($a, $b) => $a['wp_rank'] <=> $b['wp_rank']);
        return $comparison;
    }
}
?>
