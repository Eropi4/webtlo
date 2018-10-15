<?php

include_once dirname(__FILE__) . '/../phpQuery.php';
include_once dirname(__FILE__) . '/../classes/user_details.php';

class Reports {
	
	protected $ch;
	protected $login;
	protected $forum_url;
	
	private $months = array('Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec');
	private $months_ru = array( 'Янв', 'Фев', 'Мар', 'Апр', 'Май', 'Июн', 'Июл', 'Авг', 'Сен', 'Окт', 'Ноя', 'Дек' );
	
	public function __construct($forum_url, $login, $paswd){
		$this->login = $login;
		$this->forum_url = $forum_url;
		UserDetails::$forum_url = $forum_url;
		UserDetails::get_cookie( $login, $paswd );
		$this->ch = curl_init();
	}
	
	private function make_request($url, $fields = array(), $options = array()){
		curl_setopt_array($this->ch, array(
			CURLOPT_RETURNTRANSFER => 1,
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_SSL_VERIFYHOST => 2,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_MAXREDIRS => 2,
			CURLOPT_URL => $url,
			CURLOPT_COOKIE => UserDetails::$cookie,
			CURLOPT_POSTFIELDS => http_build_query($fields),
			CURLOPT_USERAGENT => "Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/55.0.2883.87 Safari/537.36",
			CURLOPT_CONNECTTIMEOUT => 20,
			CURLOPT_TIMEOUT => 20
		));
		curl_setopt_array( $this->ch, Proxy::$proxy['forum_url'] );
		curl_setopt_array($this->ch, $options);
		$try_number = 1; // номер попытки
		$try = 3; // кол-во попыток
		while ( true ) {
			$data = curl_exec( $this->ch );
			if ( $data === false ) {
				$http_code = curl_getinfo( $this->ch, CURLINFO_HTTP_CODE );
				if ( $http_code < 300 && $try_number <= $try ) {
					Log::append( "Повторная попытка $try_number/$try получить данные." );
					sleep( 5 );
					$try_number++;
					continue;
				}
				throw new Exception( "CURL ошибка: " . curl_error( $this->ch ) . " [$http_code]" );
			}
			return $data;
		}
	}
	
	// поиск темы со списком
	public function search_topic_id( $title, $forum_id = 1584 ) {
		if ( empty( $title ) ) {
			return false;
		}
		$title = html_entity_decode( $title );
		$search = preg_replace( '/.*» ?(.*)$/', '$1', $title );
		if ( mb_strlen( $search, 'UTF-8' ) < 3 ) {
			return false;
		}
		$title = explode( ' » ', $title );
		$i = 0;
		$page = 1;
		$page_id = "";
		while ( $page > 0 ) {
			$data = $this->make_request(
				$this->forum_url . "/forum/search.php?id=$page_id",
				array(
					'nm' => mb_convert_encoding( "$search", 'Windows-1251', 'UTF-8' ),
					'start' => $i,
					'f' => $forum_id
				)
			);
			$html = phpQuery::newDocumentHTML( $data, 'UTF-8' );
			unset( $data );
			$topic_main = $html->find( 'table.forum > tbody:first' );
			$pages = $html->find( 'a.pg:last' )->prev();
			if ( ! empty( $pages ) && $i == 0 ) {
				$page = $html->find( 'a.pg:last' )->prev()->text();
				$page_id = $html->find( 'a.pg:last' )->attr( 'href' );
				$page_id = preg_replace( '/.*id=([^\&]*).*/', '$1', $page_id );
			}
			unset( $html );
			if ( ! empty( $topic_main ) ) {
				$topic_main = pq( $topic_main );
				foreach ( $topic_main->find( 'tr.tCenter' ) as $row ) {
					$row = pq( $row );
					$topic_title = $row->find( 'a.topictitle' )->text();
					if ( ! empty( $topic_title ) ) {
						$topic_title = explode( '»', str_replace( '[Список] ', '', $topic_title ) );
						$topic_title = array_map( 'trim', $topic_title );
						$diff = array_diff( $topic_title, $title );
						if ( empty( $diff ) ) {
							$topic_id = $row->find( 'a.topictitle' )->attr( 'href' );
							$topic_id = preg_replace( '/.*?([0-9]*)$/', '$1', $topic_id );
							phpQuery::unloadDocuments();
							return $topic_id;
						}
					}
				}
			}
			$page--;
			$i += 50;
			phpQuery::unloadDocuments();
		}
		return false;
	}
	
	public function search_post_id( $topic_id, $last_post = false ) {
		if ( empty( $topic_id ) ) {
			return false;
		}
		$posts_ids = array();
		$i = 0;
		$page = 1;
		$page_id = "";
		while ( $page > 0 ) {
			$data = $this->make_request(
				$this->forum_url . "/forum/search.php?id=$page_id",
				array(
					'start' => $i,
					'uid' => UserDetails::$uid,
					't' => $topic_id,
					'dm' => 1
				)
			);
			$html = phpQuery::newDocumentHTML( $data, 'UTF-8' );
			unset( $data );
			$topic_main = $html->find( 'table.topic:first' );
			$pages = $html->find( 'a.pg:last' )->prev();
			if ( ! empty( $pages ) && $i == 0 ) {
				$page = $html->find( 'a.pg:last' )->prev()->text();
				$page_id = $html->find( 'a.pg:last' )->attr( 'href' );
				$page_id = preg_replace( '/.*id=([^\&]*).*/', '$1', $page_id );
			}
			unset( $html );
			if ( ! empty( $topic_main ) ) {
				$topic_main = pq( $topic_main );
				foreach ( $topic_main->find( 'tr' ) as $row ) {
					$row = pq( $row );
					$post_id = $row->find( 'a.small' )->attr( 'href' );
					if ( ! empty( $post_id ) && preg_match( '/#[0-9]+$/', $post_id ) ) {
						$post_id = preg_replace( '/.*?([0-9]*)$/', '$1', $post_id );
						if ( $last_post ) {
							phpQuery::unloadDocuments();
							return $post_id;
						}
						$posts_ids[] = $post_id;
					}
				}
			}
			$page--;
			$i += 30;
			phpQuery::unloadDocuments();
		}
		return $posts_ids;
	}

	public function scanning_viewforum( $forum_id ) {
	    if ( empty( $forum_id ) ) {
			return false;
	    }
	    $topics_ids = array();
	    $i = 0;
	    $page = 1;
	    while ( $page > 0 ) {
			$data = $this->make_request( $this->forum_url . "/forum/viewforum.php?f=$forum_id&start=$i" );
	        $html = phpQuery::newDocumentHTML( $data, 'UTF-8' );
	        unset( $data );
	        $topic_main = $html->find( 'table.forum > tr.hl-tr' );
	        $pages = $html->find( 'a.pg:last' )->prev();
	        if ( ! empty( $pages ) && $i == 0 ) {
				$page = $html->find( 'a.pg:last' )->prev()->text();
	        }
			unset( $html );
			if ( ! empty( $topic_main ) ) {
	            $topic_main = pq( $topic_main );
	            foreach ( $topic_main as $row ) {
					$row = pq( $row );
					$topic_icon = $row->find( 'img.topic_icon' )->attr( 'src' );
	                // получаем ссылки на темы со списками
					if ( preg_match ( '/.*(folder|folder_new)\.gif$/i', $topic_icon ) ) {
	                    $topic_id = $row->find( 'a.topictitle' )->attr( 'href' );
	                    $topics_ids[] = preg_replace( '/.*?([0-9]*)$/', '$1', $topic_id );
	                }
	            }
	        }
	        $page--;
			$i += 50;
			phpQuery::unloadDocuments();
	    }
	    return $topics_ids;
	}

	public function scanning_viewtopic( $topic_id, $exclude = false, $reg_days = -1 ) {
	    if ( empty( $topic_id ) ) {
			return false;
	    }
	    $keepers = array();
	    $i = 0;
		$page = 1;
		while ( $page > 0 ) {
			$data = $this->make_request( $this->forum_url . "/forum/viewtopic.php?t=$topic_id&start=$i" );
			$html = phpQuery::newDocumentHTML( $data, 'UTF-8' );
			unset( $data );
			$topic_main = $html->find( 'table#topic_main' );
			$pages = $html->find( 'a.pg:last' )->prev();
			if ( ! empty( $pages ) && $i == 0 ) {
				$page = $html->find( 'a.pg:last' )->prev()->text();
			}
			unset( $html );
			if ( ! empty( $topic_main ) ) {
				$topic_main = pq( $topic_main );
				foreach ( $topic_main->find( 'tbody' ) as $row ) {
					$row = pq( $row );
					$post_id = str_replace( 'post_', '', $row->attr( 'id' ) );
					if ( empty( $post_id ) ) {
						continue;
					}
					// если нужны только чужие посты
					$nickname = $row->find( 'p.nick > a' )->text();
					if ( $exclude && $nickname == $this->login ) {
						continue;
					}
					// вытаскиваем дату отправки/редактирования сообщения
					$posted = $row->find( '.p-link' )->text();
					$posted_since = $row->find( '.posted_since' )->text();
					if ( preg_match( '/(\d{2})-(\D{1,})-(\d{2,4}) (\d{1,2}):(\d{1,2})/', $posted_since, $since ) ) {
						$posted = $since[0];
					}
					$posted = str_replace( $this->months_ru, $this->months, $posted );
					$topic_date = DateTime::createFromFormat( 'd-M-y H:i', $posted );
					$days_diff = Date::now()->diff( $topic_date )->format( '%a' );
					// пропускаем сообщение, если оно старше $reg_days дней
					if ( $days_diff > $reg_days && $reg_days != -1 ) {
						continue;
					}
					// получаем id раздач хранимых другими хранителями
					$topics = $row->find( 'a.postLink' );
					if ( ! empty( $topics ) ) {
						foreach ( $topics as $topic ) {
							$topic = pq( $topic );
							if ( preg_match( '/viewtopic.php\?t=[0-9]+$/', $topic->attr( 'href' ) ) ) {
								$topic_id = preg_replace( '/.*?([0-9]*)$/', '$1', $topic->attr( 'href' ) );
								$keepers[] = $exclude
									? array( 'id' => $topic_id, 'nick' => $nickname )
									: $topic_id;
							}
						}
					}
					unset( $topics );
				}
			}
			$page--;
			$i += 30;
			phpQuery::unloadDocuments();
		}
	    return $keepers;
	}

	public function scanning_reports( $topic_id ) {
		if ( empty( $topic_id ) ) {
			return false;
		}
		$keepers = array();
		$i = 0;
		$page = 1;
		$index = 0;
		while ( $page > 0 ) {
			$data = $this->make_request( $this->forum_url . "/forum/viewtopic.php?t=$topic_id&start=$i" );
			$html = phpQuery::newDocumentHTML( $data, 'UTF-8' );
			unset( $data );
			$topic_main = $html->find( 'table#topic_main' );
			$pages = $html->find( 'a.pg:last' )->prev();
			if ( ! empty( $pages ) && $i == 0 ) {
				$page = $html->find( 'a.pg:last' )->prev()->text();
			}
			unset( $html );
			if ( ! empty( $topic_main ) ) {
				$topic_main = pq( $topic_main );
				foreach ( $topic_main->find( 'tbody' ) as $row ) {
					$row = pq( $row );
					$post_id = str_replace( 'post_', '', $row->attr( 'id' ) );
					if ( empty( $post_id ) ) {
						continue;
					}
					$nickname = $row->find( 'p.nick > a' )->text();
					$keepers[ $index ] = array(
						'post_id' => $post_id,
						'nickname' => $nickname
					);
					// получаем id раздач хранимых другими хранителями
					$topics = $row->find( 'a.postLink' );
					if ( ! empty( $topics ) ) {
						foreach ( $topics as $topic ) {
							$topic = pq( $topic );
							if ( preg_match( '/viewtopic.php\?t=[0-9]+$/', $topic->attr( 'href' ) ) ) {
								$topic_id = preg_replace( '/.*?([0-9]*)$/', '$1', $topic->attr( 'href' ) );
								$keepers[ $index ]['topics'][] = $topic_id;
							}
						}
					}
					unset( $topics );
					$index++;
				}
			}
			$page--;
			$i += 30;
			phpQuery::unloadDocuments();
		}
		return $keepers;
	}
	
	public function send_message($mode, $message, $topic_id, $post_id = "", $subject = ""){
		$message = str_replace('<br />', '', $message);
		$message = str_replace('[br]', "\n", $message);
		// получение form_token
		if( empty(UserDetails::$form_token) ) UserDetails::get_form_token();
		$data = $this->make_request(
			$this->forum_url . '/forum/posting.php',
			array(
				't' => $topic_id,
				'mode' => $mode,
				'p' => $post_id,
				'subject' => mb_convert_encoding("$subject", 'Windows-1251', 'UTF-8'),
				'submit_mode' => "submit",
				'form_token' => UserDetails::$form_token,
				'message' => mb_convert_encoding("$message", 'Windows-1251', 'UTF-8')
			)
		);
		$html = phpQuery::newDocumentHTML($data, 'UTF-8');
		unset( $data );
		$msg = $html->find('div.msg')->text();
		if ( ! empty( $msg ) ) {
			Log::append ( "Error: $msg ($topic_id)." );
			phpQuery::unloadDocuments();
			return;
		}
		$post_id = $html->find('div.mrg_16 > a')->attr('href');
		if ( empty( $post_id ) ) {
			$msg = $html->find('div.mrg_16')->text();
			if ( empty( $msg ) ) {
				$msg = $html->find('h2')->text();
				if ( empty( $msg ) ) {
					$msg = 'Неизвестная ошибка';
				}
			}
			Log::append ( "Error: $msg ($topic_id)." );
			phpQuery::unloadDocuments();
			return;
		}
		$post_id = preg_replace('/.*?([0-9]*)$/', '$1', $post_id);
		phpQuery::unloadDocuments();
		return $post_id;
	}
	
	public function __destruct() {
		curl_close( $this->ch );
	}
	
}

?>
