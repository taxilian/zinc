<?
class ZoneDefault extends GuiZone
{
	function pageDefault()
	{
		die('hello pehppy!');
	}
	
	function pageImportWords()
	{
		$handle = fopen("/home/rick/Sites/test/scrabble_dictionary.txt", "r");
		$words = array();
		if($handle)
		{
		    while(!feof($handle))
			{
		        $buffer = trim(fgets($handle, 4096));
		        // echo $buffer;
				$sql = "insert into word (word, len) values ('$buffer', length('$buffer'))";
				echo $sql . '<br>';
				SqlInsertRow($sql, array());
				$words[] = $buffer;
		    }
		    fclose($handle);
		}
	}
	
	function exportWordlist()
	{
		
	}
	
	function pageGenerateWordLetters()
	{
		$words = SqlFetchRows("select * from word", array());
		SqlEchoOn();
		foreach($words as $thisWord)
		{
			$letters = array();
			// echo $thisWord['word'] . '<br>';
			for($i = 0; $i < $thisWord['len']; $i++)
			{
				if(isset($letters[$thisWord['word'][$i]]))
					$letters[$thisWord['word'][$i]]++;
				else
					$letters[$thisWord['word'][$i]] = 1;
			}
			// echo_r($thisWord);
			// echo_r($letters, 1);
			
			foreach($letters as $letter => $count)
			{
				$sql = "insert into word_letter (word_id, letter, count) values (:wordId, :letter, :count)";
				SqlInsertRow($sql, array('wordId' => $thisWord['id'], 'letter' => $letter, 'count' => $count));
			}
		}
		// echo_r($words);
	}
	
	function pageGetSuggestions($p, $z)
	{
		$start = microtime(1);
		
		$tray = $p[1];
		if(isset($p[2]) && $p[2])
			$testList = array($p[2]);		
		else
		{
			$testList = array('W', 'A', 'R', 'T', 'E', 'D');
		}
		
		foreach($testList as $test)
		{
			echo "<br><br><strong>test = $test</strong><br>";

			$sql = "select
						word.word
					from
						word
						inner join word_letter on word.id = word_letter.word_id
					where";

			$all = $tray . $test;
			$len = strlen($all);
			$counts = array();
			for($i = 0; $i < $len; $i++)
			{
				if(isset($counts[$all[$i]]))
					$counts[$all[$i]]++;
				else
					$counts[$all[$i]] = 2;
			}

			$parts = array();
			for($i = 0; $i < $len; $i++)
			{
				$parts[] = "(word_letter.letter = '" . $all[$i] . "' and word_letter.count < " . $counts[$all[$i]] . ")\n";
			}
			$sql .= implode(' or ', $parts);

			$len = strlen($test);
			$parts = array();
			for($i = 0; $i < $len; $i++)
			{
				$parts[] = "(sum(case when word_letter.letter = '" . $test[$i] . "' then word_letter.count else 0 end) > 0)\n";
			}
			$testList = implode(' and ', $parts);

			$sql .= "group by
						word.id, word.word, word.len
					having
						(sum(word_letter.count) = word.len) 
						and
						$testList
					";

			// echo_r($sql);
			$rows = SqlFetchRows($sql, array());
			foreach($rows as $thisRow)
			{
				echo $thisRow['word'] . '<br>';
			}
		}
		
		$end = microtime(1);
		
		echo '<br> time = ' . ($end - $start);
	}
	
	function pageBegin($p, $z)
	{
		$this->display('begin');
	}
	
	function postBegin($p, $z)
	{
		$parsed = parse_url($_POST['url']);
		$parts = explode('&', $parsed['query']);
		$vars = array();
		foreach($parts as $thisPart)
		{
			$subs = explode('=', $thisPart);
			$vars[$subs[0]] = $subs[1];
		}
		
		// echo_r($parsed);
		// echo_r($vars);
		
		$this->redirect('board/' . $vars['gid'] . '/' . $vars['pid'] . '/' . $vars['password']);
	}
	
	function pageBoard($p, $z)
	{
		$gid = $p[1];
		$pid = $p[2];
		$password = $p[3];
		
		$numerator = rand(1, 50);
		$denominator = rand(51, 100);
		$rnd = $numerator/$denominator;
		
		$url = "http://www.scrabulousemail.com/email_scrabble/xmlv3.php?showGameOver=&gid=$gid&pid=$pid&password=$password&notify_fb=y&fb_sig_time=1192504105.5726&fb_sig_user=17824732&fb_sig_profile_update_time=1192431482&fb_sig_session_key=da16691e70ac3f1d7230f47e-17824732&fb_sig_expires=0&fb_sig_api_key=f9aad7bfa944cb308c2afac2cc1ded9c&fb_sig_added=1&fb_sig=2068649216054d7e691f57d67a0422c1&action=gameinfo&rnd=$rnd";
		$gameInfo = simplexml_load_file($url);
		
		
		$url = "http://www.scrabulousemail.com/email_scrabble/postv3.php?gid=$gid&pid=$gid&password=$password&notify_fb=y&fb_sig_time=1192504105.5726&fb_sig_user=17824732&fb_sig_profile_update_time=1192431482&fb_sig_session_key=da16691e70ac3f1d7230f47e-17824732&fb_sig_expires=0&fb_sig_api_key=f9aad7bfa944cb308c2afac2cc1ded9c&fb_sig_added=1&fb_sig=2068649216054d7e691f57d67a0422c1&action=CHECKNEW&lastmoveid=85088268&lastmsgid=4041812&rnd=$rnd";
		$boardInfo = simplexml_load_file($url);
		// echo_r($boardInfo);
		$rack = (string)$gameInfo->info->myrack[0];
		
		$board = new Board();
		
		foreach($boardInfo->t as $thisTile)
		{
			$board->setCellLetter($thisTile->r + 1, $thisTile->c + 1, $thisTile->t);
		}
		
		$sets = $board->getSets();
		// echo_r($sets);
		
		foreach($sets as $direction => $rowcols)
		{
			foreach($rowcols as $thisRowcol)
			{
				foreach($thisRowcol as $thisWord)
				{
					echo "<br><br><strong>test = $thisWord</strong><br>";
					$matches = $this->getMatches($rack, $thisWord);
					
					foreach($matches as $thisMatch)
					{
						echo $thisMatch . '<br>';
					}
				}
			}
		}
	}
	
	function getMatches($tray, $test)
	{
		$test = strtoupper($test);
		$sql = "select
					word.word
				from
					word
					inner join word_letter on word.id = word_letter.word_id
				where";
		
		$all = $tray . $test;
		$len = strlen($all);
		$counts = array();
		for($i = 0; $i < $len; $i++)
		{
			if(isset($counts[$all[$i]]))
				$counts[$all[$i]]++;
			else
				$counts[$all[$i]] = 2;
		}

		$parts = array();
		for($i = 0; $i < $len; $i++)
		{
			$parts[] = "(word_letter.letter = '" . $all[$i] . "' and word_letter.count < " . $counts[$all[$i]] . ")\n";
		}
		$sql .= implode(' or ', $parts);

		$len = strlen($test);
		$parts = array();
		for($i = 0; $i < $len; $i++)
		{
			$parts[] = "(sum(case when word_letter.letter = '" . $test[$i] . "' then word_letter.count else 0 end) > 0)\n";
		}
		$testList = implode(' and ', $parts);

		$sql .= "group by
					word.id, 
					word.word, 
					word.len
				having
					(sum(word_letter.count) = word.len) 
					and
					$testList
				";

		// echo_r($sql);
		$words = SqlFetchColumn($sql, array());
		
		return $words;
	}
}