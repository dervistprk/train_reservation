<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\Train;
use App\Models\Vagon;
use Illuminate\Http\Request;

class ReservationController extends Controller
{
    public function index()
    {
        $trains = Train::with('vagons')->get();
        return $trains;
    }

    public function show($name)
    {
        $train = Train::with('vagons')->where('adi', $name)->first();
        return $train;
    }

    public function update(Request $request, $name)
    {
        $sonuc = array('RezervasyonYapilabilir' => true, 'YerlesimAyrinti' => array());
        $train = Train::with('vagons')->where('adi', $name)->first();

        if ($request->input('KisilerFarkliVagonlaraYerlestirilebilir') !== null) {
            if ($request->input('RezervasyonYapilacakKisiSayisi') !== null && $request->input('RezervasyonYapilacakKisiSayisi') >= 1) {
                if ($request->input('KisilerFarkliVagonlaraYerlestirilebilir') == 0) {
                    if ($request->input('RezervasyonYapilacakVagonID') !== null) {
                        $vagon = Vagon::where('id', $request->input('RezervasyonYapilacakVagonID'))->first();

                        if ($vagon->doluluk_yuzdesi >= 70) {
                            $sonuc['RezervasyonYapilabilir'] = false;
                            return $sonuc;
                        }

                        $rezervasyon              = new Reservation();
                        $rezervasyon->train_id    = $train->id;
                        $rezervasyon->vagon_id    = $vagon->id;
                        $rezervasyon->kisi_sayisi = $request->input('RezervasyonYapilacakKisiSayisi');
                        $vagon->dolu_koltuk       = $vagon->dolu_koltuk + $request->input('RezervasyonYapilacakKisiSayisi');

                        $sonuc['YerlesimAyrinti'] = $rezervasyon;
                        $sonuc['Vagon']           = $vagon;

                        $rezervasyon->save();
                        $vagon->save();
                        return $sonuc;
                    } else {
                        return 'Lütfen rezervasyon yapmak istediğiniz RezervasyonYapilacakVagonID değerini giriniz!';
                    }
                } else {
                    if ($request->input('RezervasyonYapilacakVagonID') == null) {
                        if ($train->vagons->count() > 1) {
                            if ($request->input('KisilerAyniVagondaOlacak') == 1) {
                                if ($request->input('RezervasyonYapilacakKisiSayisi') > 1) {
                                    $vagons                       = Vagon::where('doluluk_yuzdesi', '<', 70)->where('train_id', $train->id)->get();
                                    $dagitim_yapilabilir_vagon_id = $vagons->pluck('id');
                                    $vagon_id                     = $dagitim_yapilabilir_vagon_id->random();
                                    $vagon                        = Vagon::findOrFail($vagon_id);
                                    $kisi_sayisi                  = $request->input('RezervasyonYapilacakKisiSayisi');
                                    $vagon_sayisi                 = $dagitim_yapilabilir_vagon_id->count();

                                    if ($vagon_sayisi == 1) {
                                        return 'Seçili trende doluluk oranı %70\' den küçük 1 adet vagon bulunduğundan dolayı yolcu dağıtımı rastgele bir vagona yapılamamaktadır!';
                                    }

                                    $rezervasyon              = new Reservation();
                                    $rezervasyon->train_id    = $train->id;
                                    $rezervasyon->vagon_id    = $vagon_id;
                                    $rezervasyon->kisi_sayisi = $kisi_sayisi;
                                    $vagon->dolu_koltuk       = $vagon->dolu_koltuk + $kisi_sayisi;
                                    $vagon->doluluk_yuzdesi   = ($vagon->dolu_koltuk * 100) / $vagon->kapasite;

                                    $sonuc['YerlesimAyrinti'] = $rezervasyon;
                                    $sonuc['Vagon']           = $vagon;

                                    $rezervasyon->save();
                                    $vagon->save();

                                    return $sonuc;
                                } else {
                                    return 'Farklı vagonlara yerleşim yapabilmek için lütfen RezervasyonYapilacakKisiSayisi değerini 1\' den büyük integer bir değer olarak giriniz!';
                                }
                            } else {
                                $response     = array('RezervasyonYapilabilir' => true);
                                $vagons       = Vagon::where('doluluk_yuzdesi', '<', 70)->where('train_id', $train->id)->get();
                                $kisi_sayisi  = $request->input('RezervasyonYapilacakKisiSayisi');
                                $vagon_sayisi = $vagons->count();
                                $vagon_ids    = $vagons->pluck('id')->toArray();

                                if ($vagon_sayisi == 1) {
                                    return 'Seçili trende doluluk oranı %70\' den küçük 1 adet vagon bulunduğundan dolayı yolcu dağıtımı farklı vagonlara yapılamamaktadır!';
                                } elseif ($vagon_sayisi == 0) {
                                    return 'Seçili trende doluluk oranı %70\' den küçük vagon bulunmadığından dolayı yolcu dağıtımı farklı vagonlara yapılamamaktadır!';
                                }

                                function yolcuDagit($kisi_sayisi, $vagon_sayisi)
                                {
                                    $numbers = [];

                                    for ($i = 1; $i < $vagon_sayisi; $i++) {
                                        $random      = mt_rand(0, $kisi_sayisi / ($vagon_sayisi - $i));
                                        $numbers[]   = $random;
                                        $kisi_sayisi -= $random;
                                    }

                                    $numbers[] = $kisi_sayisi;
                                    shuffle($numbers);
                                    return $numbers;
                                }

                                $numbers     = yolcuDagit($kisi_sayisi, $vagon_sayisi);
                                $yerlesimler = array_combine($vagon_ids, $numbers);

                                foreach ($yerlesimler as $yerlesim => $kisiler) {
                                    $rezervasyon              = new Reservation();
                                    $rezervasyon->train_id    = $train->id;
                                    $rezervasyon->vagon_id    = $yerlesim;
                                    $rezervasyon->kisi_sayisi = $kisiler;
                                    $vagon                    = Vagon::where('id', $yerlesim)->first();
                                    $vagon->dolu_koltuk       = $vagon->dolu_koltuk + $kisiler;
                                    $vagon->doluluk_yuzdesi   = ($vagon->dolu_koltuk * 100) / $vagon->kapasite;

                                    $rezervasyon->save();
                                    $vagon->save();

                                    //todo yerleşim ayrıntı dizilerinde yalnızca son rezervasyon bilgileri görünüyor. Tümünün sırayla görünmesi sağlanacak.

                                    for ($i = 1; $i < count($yerlesimler) + 1; $i++) {
                                        $yerlesim_sayisi                                = $i;
                                        $rezervasyon_bilgileri                          = Reservation::where('id', $rezervasyon->id)->first();
                                        $response['YerlesimAyrinti' . $yerlesim_sayisi] = $rezervasyon_bilgileri;
                                    }
                                }

                                $vagons_last          = Vagon::where('doluluk_yuzdesi', '<', 70)->where('train_id', $train->id)->get();
                                $response['Vagonlar'] = $vagons_last;
                                return $response;
                            }
                        } else {
                            return 'Seçmiş olduğunuz trende 1 adet vagon bulunduğundan dolayı yolcuların farklı vagonlara yerleşimi yapılamamaktadır!';
                        }
                    } else {
                        return 'KisilerFarkliVagonlaraYerlestirilebilir ve RezervasyonYapilacakVagonID değerleri aynı anda girilemez!';
                    }
                }
            } else {
                return 'Lütfen RezervasyonYapilacakKisiSayisi değerini 0\' dan büyük integer bir değer olarak giriniz!';
            }
        } else {
            return 'Lütfen kişilerin farklı vagonlara yerleşip yerleşemeyeceği bilgisini giriniz!';
        }
    }
}
