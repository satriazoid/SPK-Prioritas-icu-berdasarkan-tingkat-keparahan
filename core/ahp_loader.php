<?php
/**
 * AHP Loader - Analytical Hierarchy Process
 * Kriteria: age, bmi, d1_spo2_min, d1_heartrate_min
 * Matriks perbandingan 4x4
 */

class AHPLoader {
    
    // Nama kriteria
    private $criteria = ['age', 'bmi', 'd1_spo2_min', 'd1_heartrate_min'];
    private $criteriaLabel = ['Usia', 'BMI', 'SpO2 Min', 'Heart Rate Min'];
    
    /**
     * Matriks Perbandingan Berpasangan 4x4
     * Skala Saaty 1-9
     * Urutan: age, bmi, d1_spo2_min, d1_heartrate_min
     * 
     * Justifikasi:
     * - SpO2 paling penting (indikator kritis oksigen)
     * - Heart Rate kedua
     * - Age ketiga
     * - BMI keempat
     */
    private $pairwiseMatrix = [
        // age   bmi   spo2   hr
        [1,     2,    0.25,  0.333],  // age
        [0.5,   1,    0.2,   0.25],   // bmi
        [4,     5,    1,     2],      // spo2
        [3,     4,    0.5,   1],      // heartrate
    ];
    
    // Random Index untuk n=4
    private $randomIndex = [0, 0, 0.58, 0.90, 1.12, 1.24, 1.32, 1.41, 1.45, 1.49];
    
    private $weights = [];
    private $consistencyRatio = 0;
    private $lambdaMax = 0;
    private $consistencyIndex = 0;
    private $normalizedMatrix = [];
    private $columnSums = [];
    
    public function __construct() {
        $this->calculate();
    }
    
    /**
     * Hitung semua komponen AHP
     */
    private function calculate() {
        $n = count($this->criteria);
        
        // Step 1: Hitung jumlah tiap kolom
        $this->columnSums = array_fill(0, $n, 0);
        for ($j = 0; $j < $n; $j++) {
            for ($i = 0; $i < $n; $i++) {
                $this->columnSums[$j] += $this->pairwiseMatrix[$i][$j];
            }
        }
        
        // Step 2: Normalisasi matriks
        $this->normalizedMatrix = [];
        for ($i = 0; $i < $n; $i++) {
            for ($j = 0; $j < $n; $j++) {
                $this->normalizedMatrix[$i][$j] = $this->pairwiseMatrix[$i][$j] / $this->columnSums[$j];
            }
        }
        
        // Step 3: Hitung bobot (rata-rata tiap baris)
        $this->weights = [];
        for ($i = 0; $i < $n; $i++) {
            $rowSum = 0;
            for ($j = 0; $j < $n; $j++) {
                $rowSum += $this->normalizedMatrix[$i][$j];
            }
            $this->weights[$this->criteria[$i]] = $rowSum / $n;
        }
        
        // Step 4: Hitung Lambda Max
        $weightedSum = array_fill(0, $n, 0);
        for ($i = 0; $i < $n; $i++) {
            for ($j = 0; $j < $n; $j++) {
                $weightedSum[$i] += $this->pairwiseMatrix[$i][$j] * $this->weights[$this->criteria[$j]];
            }
        }
        
        $lambdaValues = [];
        for ($i = 0; $i < $n; $i++) {
            $lambdaValues[] = $weightedSum[$i] / $this->weights[$this->criteria[$i]];
        }
        $this->lambdaMax = array_sum($lambdaValues) / $n;
        
        // Step 5: Consistency Index
        $this->consistencyIndex = ($this->lambdaMax - $n) / ($n - 1);
        
        // Step 6: Consistency Ratio
        $this->consistencyRatio = $this->consistencyIndex / $this->randomIndex[$n];
    }
    
    /**
     * Ambil bobot kriteria
     */
    public function getWeights(): array {
        return $this->weights;
    }
    
    /**
     * Ambil bobot sebagai array terurut
     */
    public function getWeightsArray(): array {
        return array_values($this->weights);
    }
    
    /**
     * Cek apakah konsisten (CR < 0.1)
     */
    public function isConsistent(): bool {
        return $this->consistencyRatio < 0.1;
    }
    
    public function getConsistencyRatio(): float {
        return $this->consistencyRatio;
    }
    
    public function getConsistencyIndex(): float {
        return $this->consistencyIndex;
    }
    
    public function getLambdaMax(): float {
        return $this->lambdaMax;
    }
    
    public function getCriteria(): array {
        return $this->criteria;
    }
    
    public function getCriteriaLabels(): array {
        return $this->criteriaLabel;
    }
    
    public function getPairwiseMatrix(): array {
        return $this->pairwiseMatrix;
    }
    
    public function getNormalizedMatrix(): array {
        return $this->normalizedMatrix;
    }
    
    public function getColumnSums(): array {
        return $this->columnSums;
    }
    
    /**
     * Return semua detail kalkulasi AHP
     */
    public function getFullDetail(): array {
        return [
            'criteria'           => $this->criteria,
            'criteria_labels'    => $this->criteriaLabel,
            'pairwise_matrix'    => $this->pairwiseMatrix,
            'column_sums'        => $this->columnSums,
            'normalized_matrix'  => $this->normalizedMatrix,
            'weights'            => $this->weights,
            'lambda_max'         => $this->lambdaMax,
            'consistency_index'  => $this->consistencyIndex,
            'consistency_ratio'  => $this->consistencyRatio,
            'is_consistent'      => $this->isConsistent(),
        ];
    }
    
    /**
     * Normalisasi nilai pasien berdasarkan tipe kriteria
     * Cost criteria (lebih rendah = lebih baik): age, bmi
     * Benefit criteria (lebih tinggi lebih baik): spo2
     * Cost criteria: heartrate (abnormal jika terlalu tinggi/rendah)
     */
    public function getCriteriaTypes(): array {
        return [
            'age'              => 'cost',    // Usia lebih tua = lebih berisiko
            'bmi'              => 'cost',    // BMI ekstrem = lebih berisiko  
            'd1_spo2_min'      => 'cost',    // SpO2 rendah = kritis (makin rendah makin prioritas)
            'd1_heartrate_min' => 'cost',    // HR rendah = kritis
        ];
    }
}
?>
